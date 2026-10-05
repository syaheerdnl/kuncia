const rm = new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' });

/** "RM 1,234.50" */
export function formatRM(value: number | string | null | undefined): string {
    return rm.format(Number(value ?? 0)).replace('MYR', 'RM');
}
