<script lang="ts">
    let {
        size = 24,
        class: className = '',
    }: {
        size?: number;
        class?: string;
    } = $props();
</script>

<svg
    width={size}
    height={size}
    viewBox="0 0 120 80"
    class={className}
    aria-hidden="true"
>
    <g stroke="#0b1b2d" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
        <path
            d="M35 39C44 23 63 15 80 20c12 3 21 11 27 19-6 8-15 16-27 19-17 5-36-3-45-19Z"
            fill="#9bc9f5"
        />
        <path
            d="M36 36C27 26 18 22 7 24c1 9 5 15 12 20-7 5-11 11-12 20 11 2 20-2 29-12Z"
            fill="#9bc9f5"
        />
        <path
            d="M55 22C58 11 64 6 73 5c8 5 12 11 13 19"
            fill="#9bc9f5"
        />
        <path
            d="M55 56c5 6 9 10 15 12-1-6-3-11-6-15"
            fill="#9bc9f5"
        />
        <circle cx="91" cy="33" r="3.2" fill="#0b1b2d" stroke="none" />
        <path d="M82 38c4 5 5 9 4 14" fill="none" />
        <path
            d="M65 32c9 1 13 5 13 10s-4 9-13 10c5-3 8-6 8-10s-3-7-8-10Z"
            fill="none"
        />
    </g>
</svg>
