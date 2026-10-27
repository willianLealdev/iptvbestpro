<?php
/**
 * Termos de uso padrão (usados quando o texto do painel está vazio): inglês, português e espanhol.
 * A Apple exige link funcional para os Termos de Uso (EULA) dentro do app e no site (diretriz 3.1.2(c)).
 */
function terms_default_html($app, $site){
    $a = htmlspecialchars($app, ENT_QUOTES, 'UTF-8');
    $s = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    ob_start(); ?>
<div translate="no" class="tt-default">
<style>.tt-default h4{margin-top:22px}.tt-default ul{color:#5e5e5e}.tt-default section{margin-bottom:34px}</style>

<section id="en">
<h4>English</h4>
<p><?php echo $a; ?> ("the app") is a media player for the IPTV service the user already has with their own provider. The app does not provide, host, sell or include any channels, movies, series or playlists.</p>
<h4>License</h4>
<p>We grant you a personal, non-exclusive, non-transferable license to use the app on your devices. You may not copy, modify, reverse engineer, resell or misuse the app, its activation codes or its servers.</p>
<h4>Your content</h4>
<ul>
<li>You must have your own list from a provider that is authorized to distribute the content. You are responsible for the content you access and for complying with the laws of your country.</li>
<li>We are not responsible for the availability, quality or legality of content delivered by third-party servers.</li>
<li>Adult categories are hidden and can only be opened with a password you create in Settings.</li>
</ul>
<h4>Activation and yearly subscription</h4>
<ul>
<li>A device is activated with a code from your provider or reseller, or with the optional yearly subscription.</li>
<li><b>Yearly subscription (optional):</b> 1 year of use of the app with your own IPTV list (live TV, movies and series, multiple playlists, favorites, continue watching, program guide, subtitles, audio tracks and parental control) on iPhone, iPad and Apple TV. The price is shown in the app before you buy.</li>
<li>Payment is charged to your Apple account at confirmation of purchase. The subscription renews automatically for another year unless it is cancelled at least 24 hours before the end of the current period. You can manage or cancel it in Settings &gt; Apple ID &gt; Subscriptions.</li>
<li>Without a subscription or activation you can try the app with the free demo catalog.</li>
</ul>
<h4>Apple</h4>
<p>This agreement is between you and <?php echo $a; ?>, not Apple. The Apple Standard End User License Agreement (EULA) also applies to the apps distributed on the App Store: https://www.apple.com/legal/internet-services/itunes/dev/stdeula/</p>
<h4>Termination and changes</h4>
<p>We may suspend access that violates these terms. We may update these terms; continued use means you accept the changes.</p>
<h4>Contact</h4>
<p><?php echo $s; ?></p>
</section>

<section id="pt">
<h4>Português</h4>
<p>O <?php echo $a; ?> ("o app") é um reprodutor de mídia para o serviço de IPTV que o usuário já tem com o seu próprio fornecedor. O app não fornece, hospeda, vende nem inclui canais, filmes, séries ou listas.</p>
<h4>Licença</h4>
<p>Concedemos a você uma licença pessoal, não exclusiva e intransferível para usar o app nos seus aparelhos. Você não pode copiar, modificar, fazer engenharia reversa, revender nem usar de forma indevida o app, os códigos de ativação ou os servidores.</p>
<h4>Seu conteúdo</h4>
<ul>
<li>Você precisa ter a sua própria lista de um fornecedor autorizado a distribuir o conteúdo. Você é responsável pelo conteúdo que acessa e por cumprir as leis do seu país.</li>
<li>Não nos responsabilizamos pela disponibilidade, qualidade ou legalidade do conteúdo entregue por servidores de terceiros.</li>
<li>As categorias adultas ficam escondidas e só abrem com uma senha que você cria em Ajustes.</li>
</ul>
<h4>Ativação e assinatura anual</h4>
<ul>
<li>O aparelho é ativado com um código do seu fornecedor ou revenda, ou com a assinatura anual opcional.</li>
<li><b>Assinatura anual (opcional):</b> 1 ano de uso do app com a sua própria lista IPTV (TV ao vivo, filmes e séries, várias playlists, favoritos, continuar assistindo, guia de programação, legendas, faixas de áudio e controle dos pais) no iPhone, iPad e Apple TV. O preço aparece no app antes da compra.</li>
<li>O pagamento é cobrado na sua conta Apple na confirmação da compra. A assinatura renova automaticamente por mais um ano, a menos que seja cancelada até 24 horas antes do fim do período. Você gerencia ou cancela em Ajustes &gt; Apple ID &gt; Assinaturas.</li>
<li>Sem assinatura ou ativação você pode testar o app com o catálogo de demonstração gratuito.</li>
</ul>
<h4>Apple</h4>
<p>Este acordo é entre você e o <?php echo $a; ?>, não com a Apple. O Contrato de Licença de Usuário Final Padrão da Apple (EULA) também vale para os apps distribuídos na App Store: https://www.apple.com/legal/internet-services/itunes/dev/stdeula/</p>
<h4>Encerramento e alterações</h4>
<p>Podemos suspender o acesso que violar estes termos. Podemos atualizar estes termos; continuar usando significa aceitar as mudanças.</p>
<h4>Contato</h4>
<p><?php echo $s; ?></p>
</section>

<section id="es">
<h4>Español</h4>
<p><?php echo $a; ?> ("la app") es un reproductor multimedia para el servicio de IPTV que el usuario ya tiene con su propio proveedor. La app no ofrece, aloja, vende ni incluye canales, películas, series o listas.</p>
<h4>Licencia</h4>
<p>Te concedemos una licencia personal, no exclusiva e intransferible para usar la app en tus dispositivos. No puedes copiar, modificar, aplicar ingeniería inversa, revender ni usar de forma indebida la app, los códigos de activación o los servidores.</p>
<h4>Tu contenido</h4>
<ul>
<li>Debes tener tu propia lista de un proveedor autorizado a distribuir el contenido. Eres responsable del contenido al que accedes y de cumplir las leyes de tu país.</li>
<li>No somos responsables de la disponibilidad, calidad o legalidad del contenido entregado por servidores de terceros.</li>
<li>Las categorías para adultos están ocultas y solo se abren con una contraseña que creas en Ajustes.</li>
</ul>
<h4>Activación y suscripción anual</h4>
<ul>
<li>El dispositivo se activa con un código de tu proveedor o revendedor, o con la suscripción anual opcional.</li>
<li><b>Suscripción anual (opcional):</b> 1 año de uso de la app con tu propia lista IPTV (TV en vivo, películas y series, varias playlists, favoritos, seguir viendo, guía de programación, subtítulos, pistas de audio y control parental) en iPhone, iPad y Apple TV. El precio se muestra en la app antes de comprar.</li>
<li>El pago se carga a tu cuenta Apple al confirmar la compra. La suscripción se renueva automáticamente por un año más, salvo que se cancele al menos 24 horas antes del final del período. Puedes administrarla o cancelarla en Ajustes &gt; Apple ID &gt; Suscripciones.</li>
<li>Sin suscripción ni activación puedes probar la app con el catálogo de demostración gratuito.</li>
</ul>
<h4>Apple</h4>
<p>Este acuerdo es entre tú y <?php echo $a; ?>, no con Apple. El Contrato de Licencia de Usuario Final Estándar de Apple (EULA) también se aplica a las apps distribuidas en la App Store: https://www.apple.com/legal/internet-services/itunes/dev/stdeula/</p>
<h4>Terminación y cambios</h4>
<p>Podemos suspender el acceso que infrinja estos términos. Podemos actualizar estos términos; seguir usando la app significa aceptar los cambios.</p>
<h4>Contacto</h4>
<p><?php echo $s; ?></p>
</section>
</div>
<?php
    return ob_get_clean();
}
