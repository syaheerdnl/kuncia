import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';

export default function ComingSoon({ title }: { title: string }) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                <Construction className="size-10 text-muted-foreground" />
                <h1 className="text-xl font-semibold">{title}</h1>
                <p className="text-sm text-muted-foreground">
                    This module is being built.
                </p>
            </div>
        </>
    );
}
