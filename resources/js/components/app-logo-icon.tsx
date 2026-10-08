import type { SVGAttributes } from 'react';

/** Kuncia mark: a key whose head is a house ("kunci" = key). Uses currentColor. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="6 5 28 31"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <path d="M20 8 10 16v9h20v-9L20 8Z" />
            <circle
                cx="20"
                cy="18.5"
                r="2.4"
                fill="currentColor"
                stroke="none"
            />
            <path d="M20 21v12M20 29h4M20 32.5h3" />
        </svg>
    );
}
