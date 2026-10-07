import type { BillScan } from '@/components/invoices/types';

export type ShareState = 'billed' | 'waiting' | 'paid';

export type MeterBill = {
    id: number;
    period: string;
    amount: string;
    files: { name: string; url: string }[];
    shares: {
        id: number;
        tenant: string;
        unit: string;
        amount: string;
        invoice_id: number | null;
        invoice_no: string | null;
        state: ShareState;
    }[];
};

export type Meter = {
    id: number;
    type: string;
    type_label: string;
    label: string;
    account_no: string | null;
    unit_ids: number[];
    unit_codes: string[];
    bills: MeterBill[];
};

export type MeterScan = Omit<BillScan, 'suggested_description'> & {
    file_name: string;
};
