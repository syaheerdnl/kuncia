import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function AddTenantDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus /> Add tenant
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add tenant</DialogTitle>
                    <DialogDescription>
                        The tenant can log in later using "Forgot password" with this email.
                    </DialogDescription>
                </DialogHeader>
                <Form {...TenantController.store.form()} onSuccess={() => setOpen(false)} className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" name="email" type="email" required />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input id="phone" name="phone" placeholder="012-3456789" required />
                                <InputError message={errors.phone} />
                            </div>
                            <Button disabled={processing} className="w-full">
                                Add tenant
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
