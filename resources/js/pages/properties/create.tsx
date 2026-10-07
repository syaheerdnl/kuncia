import { Head } from '@inertiajs/react';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import Heading from '@/components/heading';
import { PropertyForm } from '@/components/properties/property-form';
import type { Option } from '@/components/properties/types';

export default function PropertiesCreate({
    types,
    states,
}: {
    types: Option[];
    states: string[];
}) {
    return (
        <>
            <Head title="Add property" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Add property"
                    description="Fill in the basic details. You can add units after saving."
                />
                <PropertyForm
                    form={PropertyController.store.form()}
                    types={types}
                    states={states}
                    submitLabel="Create property"
                />
            </div>
        </>
    );
}

PropertiesCreate.layout = {
    breadcrumbs: [
        { title: 'Properties', href: PropertyController.index() },
        { title: 'Add', href: PropertyController.create() },
    ],
};
