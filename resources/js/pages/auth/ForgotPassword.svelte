<script module lang="ts">
    export const layout = {
        title: 'Lupa kata sandi',
        description: 'Masukkan email untuk menerima tautan reset kata sandi.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { email } from '@/routes/password';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();
</script>

<AppHead title="Lupa kata sandi" />

{#if status}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
        {status}
    </div>
{/if}

<div class="space-y-6">
    <Form {...email.form()}>
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    placeholder="nama@apps.ipb.ac.id"
                />
                <InputError message={errors.email} />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    type="submit"
                    class="h-11 w-full rounded-xl bg-[#263c92] text-sm font-bold text-white hover:bg-[#1b2c6f]"
                    disabled={processing}
                    data-test="email-password-reset-link-button"
                >
                    {#if processing}<Spinner />{/if}
                    Kirim tautan reset
                </Button>
            </div>
        {/snippet}
    </Form>

    <div class="space-x-1 text-center text-sm text-slate-600">
        <span>Atau, kembali ke</span>
        <TextLink href={login()} class="font-semibold text-[#263c92]"
            >masuk</TextLink
        >
    </div>
</div>
