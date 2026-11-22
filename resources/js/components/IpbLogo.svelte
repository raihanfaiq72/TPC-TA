<script lang="ts">
    let {
        class: className = 'h-10 w-auto',
    }: {
        class?: string;
    } = $props();
</script>

<img
    src="/images/logo-ipb.png"
    alt="IPB University"
    class={className}
    width="500"
    height="130"
/>
