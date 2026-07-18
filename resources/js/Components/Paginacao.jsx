import { Link } from '@inertiajs/react';
import { Box, Button } from '@mui/material';

/** Paginação Inertia a partir do array `links` do paginator do Laravel. */
export default function Paginacao({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <Box sx={{ display: 'flex', gap: 0.5, flexWrap: 'wrap', justifyContent: 'center', mt: 3 }}>
            {links.map((link, i) => (
                <Button
                    key={i}
                    component={link.url ? Link : 'span'}
                    href={link.url ?? undefined}
                    size="small"
                    variant={link.active ? 'contained' : 'text'}
                    disabled={!link.url}
                    sx={{ minWidth: 36 }}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </Box>
    );
}
