<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import IpbLogo from '@/components/IpbLogo.svelte';
    import LeleIllustration from '@/components/LeleIllustration.svelte';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div class="ipb-auth min-h-svh bg-[#f5f7fc]">
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-4 py-10 sm:px-6"
    >
        <div
            class="pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-[#00aeef]/10 blur-2xl"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-36 -left-24 size-96 rounded-full bg-[#f7d84a]/20 blur-3xl"
        ></div>

        <div class="relative w-full max-w-md">
            <div class="flex flex-col items-center text-center">
                <Link
                    href="/login"
                    class="inline-flex items-center rounded-2xl bg-white px-5 py-3 shadow-[0_18px_40px_-30px_rgba(38,60,146,.6)] ring-1 ring-[#e4e8f6]"
                >
                    <IpbLogo class="h-10 w-auto" />
                </Link>
                <p
                    class="mt-4 text-[11px] font-bold tracking-[0.24em] text-[#086ad8] uppercase"
                >
                    SmartAqua · IPB University
                </p>
            </div>

            <div class="mt-7 text-center">
                <div
                    class="mx-auto mb-4 h-1 w-12 rounded-full bg-[#f7d84a]"
                ></div>
                <h1
                    class="text-2xl font-extrabold tracking-tight text-[#14235c] sm:text-3xl"
                >
                    {title}
                </h1>
                {#if description}
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500"
                    >
                        {description}
                    </p>
                {/if}
            </div>

            <div
                class="ipb-auth-card mt-6 rounded-3xl border border-[#e4e8f6] bg-white p-6 shadow-[0_28px_80px_-48px_rgba(38,60,146,.65)] sm:p-8"
            >
                {@render children?.()}
            </div>

            <div class="mt-6 flex justify-center">
                <LeleIllustration class="w-full max-w-[230px]" />
            </div>

            <p class="mt-4 text-center text-xs text-slate-400">
                © 2026 IPB University · SmartAqua
            </p>
        </div>
    </div>
</div>
