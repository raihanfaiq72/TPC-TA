<script lang="ts">
    let {
        name = 'sensor',
        size = 20,
        class: className = '',
    }: {
        name?: string;
        size?: number;
        class?: string;
    } = $props();

    const icon = $derived(name.toLowerCase());
</script>

<svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    class={className}
    aria-hidden="true"
>
    {#if icon === 'suhu'}
        <path d="M10 14.8V5a2 2 0 0 1 4 0v9.8a4 4 0 1 1-4 0Z" />
        <path d="M12 8v7M12 17.5h.01" />
    {:else if icon === 'ph'}
        <path d="M12 3.2s5.1 5.6 5.1 9.4a5.1 5.1 0 0 1-10.2 0c0-3.8 5.1-9.4 5.1-9.4Z" />
        <path d="M8.9 14.1h6.2M10.2 16.2h3.6" />
    {:else if icon === 'do'}
        <circle cx="9" cy="10" r="3.1" />
        <circle cx="16" cy="7" r="2" />
        <circle cx="15.5" cy="15.5" r="2.4" />
        <path d="M4.2 19.2c1.4-1.2 2.8-1.2 4.2 0M15.3 20.2c1.2-1 2.4-1 3.6 0" />
    {:else if icon === 'amonia'}
        <circle cx="6" cy="12" r="2.1" />
        <circle cx="18" cy="8" r="2.1" />
        <circle cx="18" cy="16" r="2.1" />
        <path d="m7.9 11.2 8.2-2.4M7.9 12.8l8.2 2.4" />
    {:else if icon === 'level'}
        <path d="M3 9c2.5-2 5-2 7.5 0s5 2 7.5 0" />
        <path d="M3 14c2.5-2 5-2 7.5 0s5 2 7.5 0" />
        <path d="M3 19c2.5-2 5-2 7.5 0s5 2 7.5 0" />
    {:else if icon === 'feeder'}
        <path d="M5 8h14v5H5z" />
        <path d="M8 13v7h8v-7M9 5h6" />
        <path d="M9 10h6" />
    {:else if icon === 'fish'}
        <path d="M3.2 12c2.7-4.2 6.4-6.2 10.2-6.2 2.6 0 4.7 1.2 5.9 3.1-1.2 2.2-3.3 3.7-5.9 3.7-3.8 0-7.5-2-10.2-3.4Z" />
        <path d="M9.8 12c1.7 1.7 3.1 2.7 4.4 3.2M18.2 8.9l2.4-2.1M18.2 15.1l2.4 2.1" />
        <circle cx="8.2" cy="10.8" r=".8" />
    {:else}
        <rect x="4" y="4" width="16" height="16" rx="3" />
        <path d="M8 9h8M8 12h5M8 15h3" />
    {/if}
</svg>
