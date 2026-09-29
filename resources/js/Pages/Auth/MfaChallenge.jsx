import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { logoutSecurely } from '@/secureHistory';
import { Head, useForm } from '@inertiajs/react';

export default function MfaChallenge() {
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

    const submit = (event) => {
        event.preventDefault();
        post(route('mfa.challenge.verify'), {
            onError: () => reset('code'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar código de segurança" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold text-gray-900">Confirme seu acesso</h1>
                    <p className="text-sm text-gray-600">
                        Digite o código atual do seu aplicativo autenticador.
                    </p>
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
                        {processing ? 'Verificando...' : 'Entrar'}
                    </PrimaryButton>
                </form>

                <button
                    type="button"
                    onClick={() => logoutSecurely(route('logout'))}
                    className="rounded-md text-sm text-gray-600 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Sair com segurança
                </button>
            </div>
        </GuestLayout>
    );
}
