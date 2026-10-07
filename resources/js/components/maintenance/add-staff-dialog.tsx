import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
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

export function AddStaffDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <UserPlus /> Add staff
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add maintenance staff</DialogTitle>
                    <DialogDescription>They log in with "Forgot password" and only see tickets you assign to them.</DialogDescription>
                </DialogHeader>
                <Form {...MaintenanceController.storeStaff.form()} onSuccess={() => setOpen(false)} className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="staff-name">Full name</Label>
                                <Input id="staff-name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="staff-email">Email</Label>
                                <Input id="staff-email" name="email" type="email" required />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="staff-phone">Phone</Label>
                                <Input id="staff-phone" name="phone" placeholder="012-3456789" required />
                                <InputError message={errors.phone} />
                            </div>
                            <Button disabled={processing} className="w-full">
                                Add staff
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
