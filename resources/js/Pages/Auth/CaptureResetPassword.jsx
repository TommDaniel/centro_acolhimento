import GuestLayout from '@/Layouts/GuestLayout';
import { takePasswordResetFragment } from '@/passwordResetFragment';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function CaptureResetPassword() {
    const captureStarted = useRef(false);

    useEffect(() => {
        if (captureStarted.current) {
            return;
        }

        captureStarted.current = true;
        const fragment = takePasswordResetFragment();

        if (fragment === null) {
            router.visit(route('password.request', undefined, false), {
                replace: true,
            });

            return;
        }

        const parameters = new URLSearchParams(fragment);
        const token = parameters.get('token');
        const email = parameters.get('email');

        if (token === null || email === null) {
            router.visit(route('password.request', undefined, false), {
                replace: true,
            });

            return;
        }

        router.post(
            route('password.reset.capture', undefined, false),
            { token, email },
            {
                preserveState: false,
                replace: true,
            },
        );
    }, []);

    return (
        <GuestLayout>
            <Head title="Validando link de recuperação" />

            <p role="status" className="text-sm text-gray-600">
                Validando o link de recuperação…
            </p>
        </GuestLayout>
    );
}
