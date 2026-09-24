import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function Forbidden() {
    const headingRef = useRef(null);

    useEffect(() => {
        headingRef.current?.focus();
    }, []);

    return (
        <GuestLayout>
            <Head title="Acesso negado" />

            <main aria-labelledby="forbidden-title" className="text-center">
                <p className="text-sm font-semibold uppercase tracking-wide text-teal-700">
                    Acesso protegido
                </p>
                <h1
                    ref={headingRef}
                    id="forbidden-title"
                    tabIndex={-1}
                    className="mt-2 text-2xl font-bold text-slate-900 focus:outline-none"
                >
                    Você não tem permissão para acessar esta área
                </h1>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    Sua conta não possui o acesso necessário. Entre novamente ou
                    fale com a administradora responsável.
                </p>
                <Link
                    href={route('login')}
                    className="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2"
                >
                    Ir para o acesso ao sistema
                </Link>
            </main>
        </GuestLayout>
    );
}
