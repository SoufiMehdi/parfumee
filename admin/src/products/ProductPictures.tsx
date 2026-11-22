import UploadIcon from '@mui/icons-material/CloudUpload';
import { Box, Button, Card, CardContent, ImageList, ImageListItem, ImageListItemBar, TextField, Typography } from '@mui/material';
import { useState, type FormEvent } from 'react';
import { useDataProvider, useNotify, useRecordContext, useRefresh } from 'react-admin';

import type { components } from '../api/schema';
import { uploadUrl } from '../config';
import type { BackOfficeDataProvider } from '../dataProvider';

// pictures may be missing: see below.
type Product = Omit<components['schemas']['ProductView'], 'pictures'> & Partial<Pick<components['schemas']['ProductView'], 'pictures'>>;

/** What the API accepts (ProductController::PICTURE_MIME_TYPES and PICTURE_MAX_SIZE). */
const ACCEPTED_TYPES = 'image/jpeg,image/png,image/webp';
const MAX_SIZE = 5 * 1024 * 1024;

/**
 * The pictures of the product, and the form that adds one. The upload is its own request
 * (multipart), apart from the product form: a picture is stored at once, then the product
 * is read again to show it.
 */
export const ProductPictures = () => {
    const product = useRecordContext<Product>();
    const dataProvider = useDataProvider<BackOfficeDataProvider>();
    const notify = useNotify();
    const refresh = useRefresh();
    const [file, setFile] = useState<File | null>(null);
    const [alt, setAlt] = useState('');
    const [sending, setSending] = useState(false);
    // A new key empties the file input after a sending: the same file can then be chosen again.
    const [inputKey, setInputKey] = useState(0);

    if (!product) {
        return null;
    }
    // Right after a creation, react-admin shows the record it sent, without pictures,
    // until it has read the product from the API.
    const pictures = product.pictures ?? [];

    const upload = async (event: FormEvent) => {
        event.preventDefault();
        if (null === file) {
            return;
        }
        // The API would refuse it anyway, once the whole file is sent.
        if (file.size > MAX_SIZE) {
            notify('L’image pèse plus de 5 Mo : elle ne peut pas être envoyée.', { type: 'error' });
            return;
        }
        setSending(true);
        try {
            await dataProvider.uploadProductPicture(product.id, file, alt);
            notify('Image ajoutée', { type: 'success' });
            setFile(null);
            setAlt('');
            setInputKey(key => key + 1);
            refresh();
        } catch (error) {
            // The API explains why it refuses a file (type, size): its message is shown.
            notify(error instanceof Error ? error.message : 'L’image n’a pas pu être envoyée.', { type: 'error' });
        } finally {
            setSending(false);
        }
    };

    return (
        <Card sx={{ ml: { md: 2 }, mt: { xs: 2, md: 0 }, width: { md: 360 }, flexShrink: 0 }}>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    Images
                </Typography>
                {0 === pictures.length ? (
                    <Typography variant="body2" color="text.secondary">
                        Aucune image pour l’instant.
                    </Typography>
                ) : (
                    <ImageList cols={2} gap={8}>
                        {pictures.map(picture => (
                            <ImageListItem key={picture.id}>
                                <img src={uploadUrl(picture.url)} alt={picture.alt ?? ''} loading="lazy" />
                                {picture.alt ? <ImageListItemBar subtitle={picture.alt} /> : null}
                            </ImageListItem>
                        ))}
                    </ImageList>
                )}
                <Box component="form" onSubmit={upload} sx={{ display: 'flex', flexDirection: 'column', gap: 1, mt: 2 }}>
                    <Button component="label" variant="outlined" startIcon={<UploadIcon />}>
                        {file ? file.name : 'Choisir une image'}
                        <input key={inputKey} hidden type="file" accept={ACCEPTED_TYPES} aria-label="Fichier de l’image" onChange={event => setFile(event.target.files?.[0] ?? null)} />
                    </Button>
                    <Typography variant="caption" color="text.secondary">
                        JPEG, PNG ou WebP, 5 Mo au plus.
                    </Typography>
                    <TextField label="Texte alternatif" size="small" value={alt} onChange={event => setAlt(event.target.value)} slotProps={{ htmlInput: { maxLength: 255 } }} />
                    <Button type="submit" variant="contained" disabled={null === file || sending}>
                        Envoyer l’image
                    </Button>
                </Box>
            </CardContent>
        </Card>
    );
};
