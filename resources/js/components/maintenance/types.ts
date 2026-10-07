export type TicketRow = {
    id: number;
    title: string;
    priority: 'low' | 'medium' | 'high';
    status: 'open' | 'in_progress' | 'resolved' | 'closed';
    unit: string;
    tenant: string;
    assignee: string | null;
    created_at: string | null;
};

export type TicketDetail = TicketRow & {
    description: string;
    assigned_to: number | null;
    resolution_note: string | null;
    resolved_at: string | null;
    photos: { id: number; url: string; name: string }[];
};
