export type Option = { value: string; label: string };

export type PropertyFormData = {
    id: number;
    name: string;
    type: string;
    address: string;
    city: string;
    state: string;
    postcode: string;
    description: string | null;
    cover_url: string | null;
};

/** One month of a property's money history (see PropertyTimeline.php). */
export type TimelineRow = {
    month: string;
    label: string;
    rent: number;
    utilities: number;
    late_fee: number;
    other: number;
    billed: number;
    collected: number;
    paid: number;
    unpaid: number;
    overdue: number;
};

export type UnitRow = {
    id: number;
    code: string;
    type: string;
    type_label: string;
    monthly_rent: string;
    deposit: string;
    status: 'vacant' | 'occupied' | 'maintenance';
    tenant: string | null;
};
