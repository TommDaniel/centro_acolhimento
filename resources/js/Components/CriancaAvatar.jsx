import { Avatar } from '@mui/material';
import { iniciais } from '@/utils/format';

/** Avatar da criança: foto quando existe, iniciais caso contrário. */
export default function CriancaAvatar({ crianca, tamanho = 48 }) {
    return (
        <Avatar
            src={crianca?.foto_url ?? undefined}
            alt={crianca?.nome_completo}
            sx={{ width: tamanho, height: tamanho, bgcolor: 'primary.main', fontWeight: 700, fontSize: tamanho * 0.36 }}
        >
            {iniciais(crianca?.nome_completo)}
        </Avatar>
    );
}
