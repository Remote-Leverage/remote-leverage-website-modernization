<?php

use App\Support\BlockDefaults;

/**
 * Title: Spanish - El Mejor Talento del Mundo
 * Slug: remote-leverage/spanish-worlds-best-talent
 * Categories: remote-leverage
 * Description: Spanish counterpart of worlds-best-talent — six benefit cards.
 *
 * Copy is transcribed verbatim from production /spanish/, not translated here, so the
 * page stays a migration rather than a re-authoring.
 */
$cards = [
    [
        'title' => 'Talento de primer nivel<br>de Latinoamérica y la UE',
        'desc' => 'Accede a talento global excepcional. Identificamos profesionales calificados con la comunicación, experiencia y confiabilidad necesarias para generar un impacto inmediato.',
    ],
    [
        'title' => 'Sin contratos,<br>Sin obligaciones',
        'desc' => 'Evalúa talento, entrevista candidatos y conoce nuestro proceso de primera mano antes de comprometerte. La decisión siempre es tuya.',
    ],
    [
        'title' => 'Sin comisiones<br>continuas de intermediarios',
        'desc' => 'Contratas talento directamente en tu empresa. Sin márgenes en la nómina, cuotas mensuales de gestión ni comisiones recurrentes.',
    ],
    [
        'title' => 'Sin pago si no encontramos<br>el talento correcto',
        'desc' => 'Nuestros incentivos están alineados con los tuyos. Solo tenemos éxito cuando logras una contratación exitosa, por eso nos enfocamos incansablemente en encontrar el candidato ideal.',
    ],
    [
        'title' => 'Soporte en pagos,<br>cumplimiento e incorporación',
        'desc' => 'Nuestra solución de Gestión de Contratistas simplifica la incorporación, contratos, nómina y cumplimiento normativo para talento internacional.',
    ],
    [
        'title' => 'Un solo panel para<br>todo tu equipo',
        'desc' => 'Gestiona nómina, contratos, cumplimiento normativo y reportes de tu fuerza laboral desde una sola plataforma. Mantente organizado a medida que tu equipo global crece.',
    ],
];

// Keep production's imagery: reuse the English section's card art, copy only differs.
$cards = array_map(
    static fn (array $card, array $source) => [...$card, 'img' => $source['img'] ?? ''],
    $cards,
    BlockDefaults::featureCards('3'),
);
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                El Mejor Talento del Mundo,<br>Contratado Directamente<br>para Ti
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Contratas talento directamente en tu empresa, sin suscripciones, sin cuotas mensuales y sin márgenes sobre el salario. Solo profesionales calificados y evaluados a fondo que te ayudan a gestionar operaciones, comunicación y organización.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderFeatureCards('3', [], $cards) ?>
</div>
<!-- /wp:group -->
