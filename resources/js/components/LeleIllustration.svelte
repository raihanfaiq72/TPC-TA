<script lang="ts">
    let {
        class: className = '',
    }: {
        class?: string;
    } = $props();

    const uid = $props.id();
    const bgId = `lele-bg-${uid}`;
    const waterId = `lele-water-${uid}`;
    const bodyId = `lele-body-${uid}`;
    const fishId = `lele-fish-${uid}`;
</script>

<svg
    viewBox="0 0 480 360"
    class={className}
    role="img"
    aria-label="Ilustrasi kolam budidaya ikan lele"
>
    <defs>
        <linearGradient id={bgId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#eaf4ff" />
            <stop offset="100%" stop-color="#cfe7ff" />
        </linearGradient>
        <linearGradient id={waterId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#8cc7f5" />
            <stop offset="100%" stop-color="#2f76bd" />
        </linearGradient>
        <linearGradient id={bodyId} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#3b86cf" />
            <stop offset="100%" stop-color="#15477f" />
        </linearGradient>
        <g id={fishId}>
            <path
                d="M26 -2 C37 -17 51 -19 57 -4 C52 11 38 15 26 2 Z"
                fill="#1b5591"
            />
            <path
                d="M31 0 L51 -13 M31 0 L53 7"
                stroke="#4f93d3"
                stroke-width="1.4"
                opacity=".65"
                fill="none"
            />
            <path d="M-4 -13 C4 -27 18 -24 22 -12 Z" fill="#1b5591" />
            <ellipse cx="0" cy="0" rx="30" ry="15" fill="url(#{bodyId})" />
            <path
                d="M-22 8 C-10 17 14 16 25 6"
                stroke="#bfe0ff"
                stroke-width="2.6"
                fill="none"
                opacity=".75"
                stroke-linecap="round"
            />
            <circle cx="-8" cy="-3" r="1.6" fill="#9fd0f7" opacity=".75" />
            <circle cx="4" cy="3" r="1.4" fill="#9fd0f7" opacity=".75" />
            <circle cx="14" cy="-4" r="1.2" fill="#9fd0f7" opacity=".75" />
            <path d="M-6 12 C-2 22 8 23 12 15 Z" fill="#1b5591" opacity=".85" />
            <circle cx="-20" cy="-4" r="2.8" fill="#0a2544" />
            <circle cx="-20.8" cy="-4.8" r=".9" fill="#ffffff" />
            <path
                d="M-14 -10 C-10 -4 -10 4 -14 10"
                stroke="#15477f"
                stroke-width="1.3"
                fill="none"
                opacity=".55"
            />
            <path
                d="M-29 2 C-38 4 -42 11 -47 13"
                stroke="#0a2544"
                stroke-width="1.8"
                fill="none"
                stroke-linecap="round"
            />
            <path
                d="M-29 -3 C-39 -6 -43 -12 -48 -14"
                stroke="#0a2544"
                stroke-width="1.8"
                fill="none"
                stroke-linecap="round"
            />
        </g>
    </defs>

    <rect width="480" height="360" rx="36" fill="url(#{bgId})" />
    <circle cx="392" cy="72" r="42" fill="#f7d84a" opacity=".18" />
    <circle cx="392" cy="72" r="28" fill="#f7d84a" />

    <path
        d="M0 168 C55 146 105 186 160 166 C215 146 265 184 320 166 C375 148 425 182 480 160 L480 324 A36 36 0 0 1 444 360 L36 360 A36 36 0 0 1 0 324 Z"
        fill="url(#{waterId})"
    />
    <path
        d="M0 168 C55 146 105 186 160 166 C215 146 265 184 320 166 C375 148 425 182 480 160"
        fill="none"
        stroke="#dff0ff"
        stroke-width="4"
        opacity=".8"
    />

    <ellipse cx="120" cy="330" rx="66" ry="12" fill="#ffffff" opacity=".1" />
    <ellipse cx="330" cy="338" rx="80" ry="14" fill="#ffffff" opacity=".08" />
    <ellipse cx="300" cy="196" rx="34" ry="7" fill="#ffffff" opacity=".16" />

    <g
        stroke="#2f9e8f"
        stroke-width="5"
        fill="none"
        stroke-linecap="round"
        opacity=".9"
    >
        <path d="M44 344 C40 306 52 284 62 260" />
        <path d="M58 344 C58 310 72 294 86 280" />
        <path d="M432 344 C436 308 426 288 416 266" />
    </g>
    <path
        d="M62 260 C78 258 88 270 84 284 C68 286 58 276 62 260 Z"
        fill="#4fb3a2"
    />
    <path
        d="M86 280 C102 276 110 288 106 300 C92 304 82 294 86 280 Z"
        fill="#3f9e8f"
    />
    <path
        d="M416 266 C400 264 392 276 396 290 C412 292 422 282 416 266 Z"
        fill="#4fb3a2"
    />

    <ellipse cx="152" cy="244" rx="58" ry="12" fill="#0b2f57" opacity=".14" />
    <ellipse cx="318" cy="292" rx="48" ry="10" fill="#0b2f57" opacity=".14" />
    <ellipse cx="346" cy="188" rx="34" ry="7" fill="#0b2f57" opacity=".12" />

    <use
        href="#{fishId}"
        transform="translate(152 216) scale(1.15) rotate(-8)"
    />
    <use
        href="#{fishId}"
        transform="translate(318 266) scale(.95) rotate(6)"
    />
    <use
        href="#{fishId}"
        transform="translate(346 170) scale(.7) rotate(-12)"
    />

    <g fill="#f7d84a">
        <circle cx="222" cy="178" r="3" />
        <circle cx="236" cy="186" r="2.6" />
        <circle cx="208" cy="190" r="2.4" />
        <circle cx="250" cy="176" r="2.2" />
        <circle cx="196" cy="176" r="2" />
    </g>

    <g fill="#ffffff" opacity=".5">
        <circle cx="196" cy="204" r="4" />
        <circle cx="188" cy="218" r="2.6" />
        <circle cx="204" cy="226" r="2" />
        <circle cx="360" cy="206" r="3.4" />
        <circle cx="370" cy="220" r="2.2" />
    </g>
</svg>
