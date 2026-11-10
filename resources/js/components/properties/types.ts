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

/** One thing that happened at a property (see PropertyTimeline.php). Dates are Y-m-d. */
export type TimelineEvent =
    | {
          type: 'payment';
          date: string;
          amount: string;
          method: string;
          reference: string | null;
          period: string;
          tenant: string;
          unit: string;
      }
    | {
          type: 'tenancy_start';
          date: string;
          amount: string;
          deposit: string;
          tenant: string;
          unit: string;
      }
    | {
          type: 'tenancy_end';
          date: string;
          amount: string;
          deposit: string;
          deposit_status: string;
          tenant: string;
          unit: string;
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
