import {
    Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle,
} from '@mui/material';

/** Diálogo de confirmação (ex.: exclusões). */
export default function ConfirmDialog({ aberto, titulo, mensagem, aoCancelar, aoConfirmar, processando = false }) {
    return (
        <Dialog open={aberto} onClose={aoCancelar} maxWidth="xs" fullWidth>
            <DialogTitle>{titulo}</DialogTitle>
            <DialogContent>
                <DialogContentText>{mensagem}</DialogContentText>
            </DialogContent>
            <DialogActions sx={{ px: 3, pb: 2 }}>
                <Button onClick={aoCancelar} color="inherit">Cancelar</Button>
                <Button onClick={aoConfirmar} color="error" variant="contained" disabled={processando}>
                    Confirmar
                </Button>
            </DialogActions>
        </Dialog>
    );
}
