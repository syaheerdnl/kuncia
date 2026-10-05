import { Head } from '@inertiajs/react';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import Heading from '@/components/heading';
import { PropertyForm } from '@/components/properties/property-form';
import type { Option, PropertyFormData } from '@/components/properties/types';

type Props = { property: PropertyFormData; types: Option[]; states: string[] };

export default function PropertiesEdit({ property, types, states }: Props) {
    return (
        <>
            <Head title={`Edit ${property.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading title="Edit property" description={property.name} />
                <PropertyForm
                    form={PropertyController.update.form(property.id)}
                    property={property}
                    types={types}
                    states={states}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

PropertiesEdit.layout = {
    breadcrumbs: [{ title: 'Properties', href: PropertyController.index() }],
};
