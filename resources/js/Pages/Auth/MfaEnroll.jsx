import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function MfaEnroll({ enrollmentId, qrCodeSvg, setupKey }) {
    const { data, setData, post, processing, errors } = useForm({
        enrollment_id: enrollmentId,
        code: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('mfa.enrollment.confirm'));
    };

    return (
        <GuestLayout>
            <Head title="Ativar autenticação em duas etapas" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold text-gray-900">Proteja seu acesso</h1>
                    <p className="text-sm text-gray-600">
                        Escaneie o QR Code com o FreeOTP ou outro aplicativo autenticador. Depois, informe o código de seis dígitos.
                    </p>
                </div>

                <div
                    aria-label="QR Code para vincular o aplicativo autenticador"
                    className="mx-auto rounded-xl border border-gray-200 bg-white p-3"
                    dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                />

                <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <p className="font-medium">Não consegue escanear?</p>
                    <p>Digite esta chave no aplicativo autenticador:</p>
                    <code aria-label="Chave de configuração do autenticador" className="mt-1 block break-all font-mono font-semibold">
                        {setupKey}
                    </code>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <InputLabel htmlFor="code" value="Código de segurança" />
                        <TextInput
                            id="code"
                            name="code"
                            value={data.code}
                            className="mt-1 block w-full"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            isFocused
                            onChange={(event) => setData('code', event.target.value.replace(/\D/g, ''))}
                            aria-describedby={errors.code ? 'code-error' : undefined}
                        />
                        <InputError id="code-error" message={errors.code} className="mt-2" />
                    </div>

                    <PrimaryButton disabled={processing} className="justify-center">
                        {processing ? 'Confirmando...' : 'Ativar proteção'}
                    </PrimaryButton>
                </form>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="rounded-md text-sm text-gray-600 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Sair com segurança
                </Link>
            </div>
        </GuestLayout>
    );
}
