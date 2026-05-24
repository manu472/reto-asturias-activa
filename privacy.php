<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

render_header('Politica de privacidad', [
    'description' => 'Politica de privacidad de Reto Asturias Activa sobre cuentas de usuario, comentarios, suscripciones premium y derechos de las personas usuarias.',
    'canonical' => 'privacy.php',
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Privacidad</span>
    <h1>Politica de privacidad</h1>
    <p>Explicamos de forma clara que datos puede tratar la plataforma, con que finalidad y que derechos conservan las personas usuarias.</p>
</section>

<section class="card legal-document" style="margin-top: 14px;">
    <div class="legal-meta">Ultima actualizacion: 29/04/2026</div>
    <h2 class="section-title">1. Responsable y alcance</h2>
    <p class="content-prose">Reto Asturias Activa actua como responsable del tratamiento de los datos personales que se recogen a traves del sitio en la medida necesaria para prestar las funcionalidades de la plataforma. Esta politica se aplica a los datos introducidos por las personas usuarias durante el registro, el uso de funciones comunitarias y la contratacion del plan premium.</p>

    <h2 class="section-title">2. Datos que pueden tratarse</h2>
    <ul class="feature-list">
        <li>Datos de cuenta: nombre, correo electronico, credenciales cifradas y estado de verificacion.</li>
        <li>Datos de uso: rutas completadas, favoritas, comentarios, propuestas de rutas, retos y puntuaciones.</li>
        <li>Datos vinculados al premium: estado de suscripcion, pagos registrados y renovaciones.</li>
        <li>Datos tecnicos minimos necesarios para sesion, seguridad y funcionamiento del sitio.</li>
    </ul>

    <h2 class="section-title">3. Finalidades del tratamiento</h2>
    <ul class="feature-list">
        <li>Crear y mantener la cuenta de usuario.</li>
        <li>Prestar funciones como favoritos, comentarios, retos, ranking y panel personal.</li>
        <li>Gestionar el acceso al plan premium y sus renovaciones.</li>
        <li>Prevenir abusos, reforzar seguridad y moderar el contenido enviado a la comunidad.</li>
        <li>Mejorar la experiencia general del servicio y su evolucion funcional.</li>
    </ul>

    <h2 class="section-title">4. Base juridica</h2>
    <p class="content-prose">La base juridica principal es la ejecucion de la relacion de servicio con la persona usuaria. En determinados casos puede existir tambien interes legitimo para proteger la seguridad, moderar contenido o mantener la integridad del sistema. Cuando proceda, determinadas acciones se apoyaran en el consentimiento de la persona usuaria.</p>

    <h2 class="section-title">5. Conservacion</h2>
    <p class="content-prose">Los datos se conservaran durante el tiempo necesario para mantener la cuenta y prestar el servicio, asi como durante los plazos razonables para atender incidencias, obligaciones legales o defensa frente a reclamaciones. Cuando dejen de ser necesarios, se suprimiran o bloquearan segun corresponda.</p>

    <h2 class="section-title">6. Destinatarios y terceros</h2>
    <p class="content-prose">Con caracter general, los datos no se ceden a terceros salvo cuando sea necesario para prestar el servicio, cumplir obligaciones legales o integrar herramientas externas como pasarelas de pago. En esos casos, cada tercero actuara conforme a sus propias condiciones y responsabilidades.</p>

    <h2 class="section-title">7. Derechos de las personas usuarias</h2>
    <p class="content-prose">Las personas usuarias pueden solicitar acceso, rectificacion, supresion, limitacion, oposicion o portabilidad de sus datos cuando resulte aplicable. Tambien pueden retirar el consentimiento que hubieran otorgado en aquellos tratamientos basados especificamente en el mismo.</p>

    <h2 class="section-title">8. Seguridad</h2>
    <p class="content-prose">La plataforma aplica medidas tecnicas y organizativas razonables para proteger la informacion, incluyendo control de acceso, validaciones de seguridad y tratamiento cuidadoso de operaciones sensibles como autenticacion o pagos.</p>

    <h2 class="section-title">9. Cambios</h2>
    <p class="content-prose">Esta politica puede actualizarse para reflejar mejoras funcionales, cambios legales o nuevas integraciones. Cuando el cambio sea relevante, se reflejara en la fecha de actualizacion del documento.</p>
</section>
<?php render_footer(); ?>
