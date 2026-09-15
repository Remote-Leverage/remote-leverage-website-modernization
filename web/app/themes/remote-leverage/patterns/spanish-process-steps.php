<?php

use App\Support\BlockDefaults;

/**
 * Title: Spanish - De Vacante a Incorporado en 4 Días
 * Slug: remote-leverage/spanish-process-steps
 * Categories: remote-leverage
 * Description: Spanish counterpart of process-steps — the 3-step hiring timeline.
 */
$steps = [
    ['num' => '01', 'title' => 'Dinos qué buscas en<br>tu candidato ideal', 'desc' => 'Nosotros nos encargamos de la búsqueda, filtrado y evaluación de candidatos para que puedas enfocarte en elegir a la persona correcta.'],
    ['num' => '02', 'title' => 'Conoce tu lista<br>del top 1%', 'desc' => 'En 48–72 horas, recibe de 4 a 6 candidatos preevaluados por habilidad, experiencia y compatibilidad. Tú entrevistas, tú eliges. Sin compromisos, sin presión.'],
    ['num' => '03', 'title' => 'Elige quién se une<br>a tu equipo', 'desc' => 'Haz tu selección y vuelve a enfocarte en hacer crecer tu negocio. Nosotros nos encargamos de los detalles para que tu nueva contratación pueda comenzar de inmediato.'],
];
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                De Vacante a<br>Incorporado en 4 Días
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Dinos a quién necesitas. Buscamos, evaluamos y presentamos candidatos calificados en pocos días, ayudándote a pasar de una vacante abierta a un miembro productivo del equipo más rápido que con la contratación tradicional.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderHireVa4ProcessSteps([], $steps) ?>
</div>
<!-- /wp:group -->
