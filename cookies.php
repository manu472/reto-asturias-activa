<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

render_header('Politica de cookies', [
    'description' => 'Politica de cookies de Reto Asturias Activa con detalle de cookies tecnicas, de sesion y futuras integraciones si se activan.',
    'canonical' => 'cookies.php',
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Cookies</span>
    <h1>Politica de cookies</h1>
    <p>Esta pagina explica que cookies utiliza actualmente la web y con que finalidad, de forma alineada con el funcionamiento real del proyecto.</p>
</section>

<section class="card legal-document" style="margin-top: 14px;">
    <div class="legal-meta">Ultima actualizacion: 29/04/2026</div>
    <h2 class="section-title">1. Que son las cookies</h2>
    <p class="content-prose">Las cookies son pequenos ficheros que el navegador almacena para recordar informacion sobre una visita, una sesion o determinadas preferencias de uso.</p>

    <h2 class="section-title">2. Cookies actualmente utilizadas</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cookie</th>
                    <th>Tipo</th>
                    <th>Finalidad</th>
                    <th>Duracion aproximada</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PHPSESSID</td>
                    <td>Tecnica / sesion</td>
                    <td>Mantener la sesion del usuario, autenticacion y funcionamiento basico del sitio.</td>
                    <td>Sesion</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="section-title">3. Cookies no esenciales</h2>
    <p class="content-prose">En el estado actual del proyecto no se han detectado cookies publicitarias ni de analitica propias configuradas a nivel funcional. Si en el futuro se activan servicios de medicion, personalizacion avanzada o terceros adicionales, esta politica debera actualizarse y, cuando proceda, se debera recabar el consentimiento correspondiente.</p>

    <h2 class="section-title">4. Gestion y desactivacion</h2>
    <p class="content-prose">La persona usuaria puede borrar o bloquear cookies desde la configuracion de su navegador. No obstante, la desactivacion de cookies tecnicas puede afectar al inicio de sesion, la seguridad y el funcionamiento general del sitio.</p>

    <h2 class="section-title">5. Actualizaciones</h2>
    <p class="content-prose">Esta politica podra modificarse si cambian las funcionalidades del sitio o se incorporan nuevas integraciones tecnicas que impliquen el uso de otras cookies.</p>
</section>
<?php render_footer(); ?>
