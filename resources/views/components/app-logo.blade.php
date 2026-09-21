@props([
    'sidebar' => false,
    'href' => null,
])

{{--
    AVYTRA wordmark. Letters and the "A" structure use currentColor so the same
    component works as the primary (Ink) or white logo; the arrow is always Lime.
    Minimum digital size is 96px wide (brand manual, p. 6).
--}}
<a
    href="{{ $href ?? route('home') }}"
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center text-ink dark:text-white']) }}
    aria-label="{{ config('app.name', 'AVYTRA') }}"
>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 650 120" class="{{ $sidebar ? 'h-7' : 'h-8' }} w-auto" aria-hidden="true">
        <g transform="translate(10,7) scale(0.4140625)">
            <path d="M52 196 L121 56 Q128 42 135 56 L204 196" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M88 144 H161" fill="none" stroke="#B8F34A" stroke-width="20" stroke-linecap="round"/>
            <path d="M151 124 L181 144 L151 164 Z" fill="#B8F34A"/>
        </g>
        <g fill="currentColor">
            <path d="M918 551 761 995Q747 1030 731.5 1077.0Q716 1124 702 1179Q688 1123 672.5 1075.5Q657 1028 643 993L487 551ZM1422 0H1189Q1150 0 1125.5 18.5Q1101 37 1089 66L991 343H413L315 66Q305 41 280.0 20.5Q255 0 217 0H-18L549 1451H856Z" transform="translate(136.00,88.00) scale(0.043500,-0.043500)"/>
            <path d="M1422 1451 838 0H566L-18 1451H224Q263 1451 287.5 1432.5Q312 1414 324 1385L638 549Q656 502 673.5 446.5Q691 391 706 330Q719 391 734.5 446.5Q750 502 768 549L1080 1385Q1090 1410 1115.5 1430.5Q1141 1451 1179 1451Z" transform="translate(200.12,88.00) scale(0.043500,-0.043500)"/>
            <path d="M808 558V0H508V558L-19 1451H245Q284 1451 307.5 1432.5Q331 1414 345 1385L583 928Q607 882 626.5 842.0Q646 802 661 762Q675 802 693.5 842.5Q712 883 735 928L971 1385Q983 1409 1007.0 1430.0Q1031 1451 1069 1451H1335Z" transform="translate(264.24,88.00) scale(0.043500,-0.043500)"/>
            <path d="M1179 1209H755V0H454V1209H30V1451H1179Z" transform="translate(324.53,88.00) scale(0.043500,-0.043500)"/>
            <path d="M606 764Q679 764 732.0 782.5Q785 801 819.0 833.5Q853 866 869.0 910.0Q885 954 885 1006Q885 1109 816.5 1166.0Q748 1223 608 1223H452V764ZM1309 0H1038Q962 0 928 58L652 503Q635 529 614.5 541.0Q594 553 554 553H452V0H152V1451H608Q760 1451 868.0 1419.5Q976 1388 1045.0 1332.0Q1114 1276 1146.0 1198.5Q1178 1121 1178 1028Q1178 956 1157.5 891.5Q1137 827 1098.0 774.0Q1059 721 1002.0 680.0Q945 639 872 614Q901 598 926.0 575.5Q951 553 971 522Z" transform="translate(380.12,88.00) scale(0.043500,-0.043500)"/>
            <path d="M918 551 761 995Q747 1030 731.5 1077.0Q716 1124 702 1179Q688 1123 672.5 1075.5Q657 1028 643 993L487 551ZM1422 0H1189Q1150 0 1125.5 18.5Q1101 37 1089 66L991 343H413L315 66Q305 41 280.0 20.5Q255 0 217 0H-18L549 1451H856Z" transform="translate(439.67,88.00) scale(0.043500,-0.043500)"/>
        </g>
    </svg>
</a>
