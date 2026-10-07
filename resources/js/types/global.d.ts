import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            canRegister: boolean;
            guestLogin: { email: string; password: string } | null;
            isGuest: boolean;
            [key: string]: unknown;
        };
    }
}
