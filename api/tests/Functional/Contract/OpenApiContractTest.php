<?php

declare(strict_types=1);

namespace App\Tests\Functional\Contract;

use App\Ecommerce\Infrastructure\EventListener\ExceptionListener;
use App\Ecommerce\Infrastructure\Security\JwtFailureListener;
use App\Tests\Support\AuthenticatesRequests;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * The OpenAPI contract is what the fronts generate their client from: it has
 * to describe every route, and what it says must be what the API answers.
 */
final class OpenApiContractTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string SPEC_FILE = __DIR__.'/../../../openapi.json';
    private const string ID = '11111111-1111-4111-8111-111111111111';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTheContractIsServedPubliclyAsJson(): void
    {
        $spec = $this->fetchSpec();

        self::assertStringStartsWith('3.', $spec['openapi']);
        self::assertSame('Parfumée API', $spec['info']['title']);
    }

    public function testSwaggerUiIsServedPublicly(): void
    {
        $this->client->request('GET', '/api/v1/doc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('swagger-ui', (string) $this->client->getResponse()->getContent());
    }

    /**
     * A route added without its documentation, or documented under another path, fails here.
     */
    public function testEveryRouteOfTheApiIsDocumentedAndNothingElse(): void
    {
        $documented = [];
        foreach ($this->fetchSpec()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper((string) $method).' '.$path;
            }
        }

        $routed = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $route) {
            if (!preg_match('#^/api/v1/(shop|admin|token)/#', $route->getPath())) {
                continue;
            }
            foreach ($route->getMethods() as $method) {
                $routed[] = $method.' '.$route->getPath();
            }
        }

        sort($documented);
        sort($routed);
        self::assertNotEmpty($routed);
        self::assertSame($routed, $documented);
    }

    public function testEveryOperationHasASummaryATagAndASuccessResponse(): void
    {
        foreach ($this->operations() as $name => $operation) {
            self::assertNotEmpty($operation['summary'] ?? '', $name.' has no summary.');
            self::assertNotEmpty($operation['tags'] ?? [], $name.' has no tag.');
            self::assertNotEmpty(
                array_filter(array_keys($operation['responses']), static fn (int|string $code): bool => str_starts_with((string) $code, '2')),
                $name.' documents no success response.',
            );
        }
    }

    /**
     * The contract must say "public" exactly where access_control does: an anonymous
     * request is sent to every operation, and only the public ones may pass the firewall.
     */
    public function testAnOperationIsDocumentedAsPublicExactlyWhenItIs(): void
    {
        $spec = $this->fetchSpec();
        self::assertSame([['Bearer' => []]], $spec['security'], 'Operations must require a token by default.');

        foreach ($this->operations() as $name => $operation) {
            [$method, $path] = explode(' ', $name);
            $documentedAsPublic = [] === ($operation['security'] ?? null);

            // A fresh client each time: the kernel is rebooted between two requests anyway.
            self::ensureKernelShutdown();
            $client = static::createClient();
            $client->request($method, str_replace('{id}', self::ID, $path), server: ['CONTENT_TYPE' => 'application/json'], content: 'not-json');
            $status = $client->getResponse()->getStatusCode();

            if ($documentedAsPublic) {
                self::assertNotSame(403, $status, $name.' is documented as public but answers 403.');
                if (401 === $status) {
                    // A public operation may still answer 401 on its own (wrong credentials), never for a missing token.
                    self::assertStringNotContainsString(JwtFailureListener::MISSING, (string) $client->getResponse()->getContent(), $name.' is documented as public but requires a token.');
                }
                self::assertArrayNotHasKey('403', $operation['responses'], $name.' is public: it cannot answer 403.');
            } else {
                self::assertSame(401, $status, $name.' is documented as protected but an anonymous request got '.$status.'.');
                self::assertArrayHasKey('401', $operation['responses'], $name.' is protected: it must document 401.');
            }
        }
    }

    public function testBackOfficeOperationsDocumentTheForbiddenResponse(): void
    {
        foreach ($this->operations() as $name => $operation) {
            if (!str_contains($name, '/api/v1/admin/') || [] === ($operation['security'] ?? null)) {
                continue;
            }

            self::assertArrayHasKey('403', $operation['responses'], $name.' is restricted to a role: it must document 403.');
        }
    }

    /**
     * A list is always one page with its pagination, never a bare array: a bare array
     * cannot tell how many items there are in all, nor grow a field later.
     */
    public function testEveryListIsOnePageWithItsPagination(): void
    {
        $spec = $this->fetchSpec();
        $schemas = $spec['components']['schemas'];

        $lists = 0;
        foreach ($this->operations() as $name => $operation) {
            $success = $operation['responses']['200'] ?? $operation['responses']['201'] ?? [];
            $schema = $success['content']['application/json']['schema'] ?? [];
            self::assertNotSame('array', $schema['type'] ?? null, $name.' returns a bare array: return a page with its pagination.');

            $referenced = isset($schema['$ref']) ? $schemas[basename((string) $schema['$ref'])] : [];
            if ('array' !== ($referenced['properties']['items']['type'] ?? null)) {
                continue;
            }

            ++$lists;
            self::assertSame('#/components/schemas/PaginationView', $referenced['properties']['pagination']['$ref'] ?? null, $name.' returns items without their pagination.');
            self::assertSame(['$ref' => '#/components/headers/TotalCount'], $success['headers']['X-Total-Count'] ?? null, $name.' does not document the X-Total-Count header.');
            $parameters = array_column($operation['parameters'] ?? [], 'name');
            foreach (['page', 'per_page', 'sort', 'order'] as $parameter) {
                self::assertContains($parameter, $parameters, $name.' is a list without the "'.$parameter.'" parameter.');
            }
        }

        // The shop products, the back-office products and the accounts.
        self::assertSame(3, $lists);
    }

    public function testEveryReferenceOfTheContractResolves(): void
    {
        $spec = $this->fetchSpec();
        $references = [];
        array_walk_recursive($spec, static function (mixed $value, int|string $key) use (&$references): void {
            if ('$ref' === $key && \is_string($value)) {
                $references[$value] = true;
            }
        });

        self::assertNotEmpty($references);
        foreach (array_keys($references) as $reference) {
            self::assertStringStartsWith('#/components/', $reference);
            [, , $section, $name] = explode('/', $reference);
            self::assertArrayHasKey($name, $spec['components'][$section] ?? [], $reference.' points to nothing.');
        }
    }

    public function testRequestAndResponseFieldsAreAllInSnakeCase(): void
    {
        foreach ($this->fetchSpec()['components']['schemas'] as $name => $schema) {
            foreach (array_keys($schema['properties'] ?? []) as $property) {
                self::assertMatchesRegularExpression('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', (string) $property, \sprintf('%s.%s is not in snake_case.', $name, $property));
            }
        }
    }

    /**
     * Error bodies are built by hand in listeners, not from a class: this is
     * what ties them to the Error schema of the contract.
     *
     * No request here may reach the database: functional tests run without one. The
     * refused token refresh, which reads it, is checked in TokenRefreshTest.
     */
    public function testRealErrorResponsesMatchTheErrorSchema(): void
    {
        $schema = $this->fetchSpec()['components']['schemas']['Error'];
        self::assertSame(['error'], $schema['required']);

        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', '/api/v1/shop/me');
        self::assertSame(401, $client->getResponse()->getStatusCode());
        self::assertSame(['error' => JwtFailureListener::MISSING], $this->decode($client));

        $client->request('GET', '/api/v1/shop/me', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);
        self::assertSame(401, $client->getResponse()->getStatusCode());
        self::assertSame(['error' => JwtFailureListener::INVALID], $this->decode($client));

        $client->request('GET', '/api/v1/shop/products/not-a-uuid');
        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame(['error'], array_keys($this->decode($client)));
    }

    public function testARealForbiddenResponseMatchesTheErrorSchema(): void
    {
        $this->authenticateAs($this->client, self::ID);

        $this->client->request('GET', '/api/v1/admin/users');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(['error'], array_keys($this->decode($this->client)));
    }

    public function testARealValidationErrorMatchesTheValidationErrorSchema(): void
    {
        $schema = $this->fetchSpec()['components']['schemas']['ValidationError'];

        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('POST', '/api/v1/shop/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email": "nope", "password": "x", "first_name": "", "last_name": "A"}');

        self::assertSame(422, $client->getResponse()->getStatusCode());
        $body = $this->decode($client);
        self::assertSame($schema['required'], array_keys($body));
        self::assertSame(ExceptionListener::VALIDATION_FAILED_MESSAGE, $body['error']);
        self::assertNotEmpty($body['violations']);
        foreach ($body['violations'] as $violation) {
            self::assertSame($schema['properties']['violations']['items']['required'], array_keys($violation));
        }
    }

    /**
     * The contract is committed, so that a change of the API shows up in the diff of a pull request.
     */
    public function testTheCommittedContractIsUpToDate(): void
    {
        self::assertFileExists(self::SPEC_FILE, 'Run "composer openapi:dump".');

        $committed = json_decode((string) file_get_contents(self::SPEC_FILE), true, flags: \JSON_THROW_ON_ERROR);
        $current = $this->fetchSpec();
        // The server URL depends on where the contract is asked from.
        unset($committed['servers'], $current['servers']);

        self::assertSame($current, $committed, 'api/openapi.json is out of date: run "composer openapi:dump" and commit the result.');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSpec(): array
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', '/api/v1/doc.json');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        return $this->decode($client);
    }

    /**
     * @return array<string, array<string, mixed>> operations, keyed by "METHOD /path"
     */
    private function operations(): array
    {
        $operations = [];
        foreach ($this->fetchSpec()['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $operations[strtoupper((string) $method).' '.$path] = $operation;
            }
        }

        return $operations;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
