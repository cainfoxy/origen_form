<?php
/**
 * Plugin Name: Origen Sostenible - Formulario Evaluación Energética
 * Plugin URI: https://www.origensostenible.net
 * Description: Formulario de evaluación energética con cálculo de presupuesto para instalaciones solares fotovoltaicas.
 * Version: 1.5.3
 * Author: Origen Sostenible SL
 * Author URI: https://www.origensostenible.net
 * Text Domain: origen-sostenible-form
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ORIGEN_FORM_VERSION', '1.5.3');
define('ORIGEN_FORM_PATH', plugin_dir_path(__FILE__));
define('ORIGEN_FORM_URL', plugin_dir_url(__FILE__));

require_once ORIGEN_FORM_PATH . 'budget-calculator.php';

// =============================================================================
// ACTIVACIÓN: Crear tabla en base de datos
// =============================================================================

register_activation_hook(__FILE__, 'origen_activate_plugin');

function origen_activate_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'origen_form_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        property_type varchar(50),
        consumption_type varchar(10),
        consumption_value varchar(50),
        services text,
        timeframe varchar(50),
        location varchar(255),
        additional_info text,
        name varchar(255),
        email varchar(255),
        phone varchar(100),
        privacy_accepted varchar(10),
        commercial_accepted varchar(10),
        wants_quote varchar(10),
        roof_type varchar(50),
        roof_orientation varchar(50),
        roof_surface varchar(50),
        financial_capacity varchar(100),
        decision_making varchar(100),
        motivation varchar(100),
        consumption_other_kwh varchar(50),
        consumption_other_euros varchar(50),
        roof_surface_other varchar(50),
        battery_option varchar(50),
        wants_ve_charger varchar(10),
        contact_preference varchar(50),
        adjustment_reason varchar(50),
        recommended_power varchar(50),
        recommended_panels int,
        base_price decimal(10,2),
        battery_price decimal(10,2),
        ve_price decimal(10,2),
        total_price decimal(10,2),
        annual_savings decimal(10,2),
        payback_years decimal(4,2),
        ip_address varchar(100),
        user_agent text,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Precios por defecto
    if (!get_option('origen_installation_prices')) {
        $default_installations = array(
            array('power' => 2, 'panels' => 4, 'price' => 2820),
            array('power' => 3, 'panels' => 6, 'price' => 3815),
            array('power' => 4, 'panels' => 7, 'price' => 4475),
            array('power' => 5, 'panels' => 9, 'price' => 5385),
            array('power' => 6, 'panels' => 11, 'price' => 6395),
            array('power' => 8, 'panels' => 14, 'price' => 7585),
            array('power' => 10, 'panels' => 17, 'price' => 8870),
        );
        update_option('origen_installation_prices', $default_installations);
    }

    if (!get_option('origen_battery_prices')) {
        $default_batteries = array(
            '5kwh'  => 1356.75,
            '10kwh' => 2451.15,
            '15kwh' => 3536.55,
        );
        update_option('origen_battery_prices', $default_batteries);
    }

    if (!get_option('origen_ve_charger_price')) {
        update_option('origen_ve_charger_price', 995);
    }
}

// =============================================================================
// ENQUEUE: Estilos y scripts frontend
// =============================================================================

add_action('wp_enqueue_scripts', 'origen_enqueue_frontend');

function origen_enqueue_frontend() {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'origen_form')) {
        return;
    }

    wp_enqueue_style(
        'origen-form-css',
        ORIGEN_FORM_URL . 'css/origen-form.css',
        array(),
        ORIGEN_FORM_VERSION
    );

    wp_enqueue_script(
        'origen-form-js',
        ORIGEN_FORM_URL . 'js/origen-form.js',
        array('jquery'),
        ORIGEN_FORM_VERSION,
        true
    );

    $calculator = new Origen_Budget_Calculator();

    wp_localize_script('origen-form-js', 'origenForm', array(
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('origen_form_nonce'),
        'installations' => $calculator->get_installations(),
        'batteries'     => $calculator->get_batteries(),
        've_charger'    => $calculator->get_ve_charger_price(),
        'tech_params'   => $calculator->get_technical_params(),
    ));
}

// =============================================================================
// ENQUEUE: Estilos admin
// =============================================================================

add_action('admin_enqueue_scripts', 'origen_enqueue_admin');

function origen_enqueue_admin($hook) {
    if (strpos($hook, 'origen-form') === false) {
        return;
    }
    wp_enqueue_style(
        'origen-admin-css',
        ORIGEN_FORM_URL . 'css/origen-admin.css',
        array(),
        ORIGEN_FORM_VERSION
    );
}

// =============================================================================
// SHORTCODE: [origen_form]
// =============================================================================

add_shortcode('origen_form', 'origen_render_form');

function origen_render_form() {
    ob_start();
    ?>
    <div id="origenFormWrapper" class="origen-form-wrapper">

        <!-- Barra de progreso -->
        <div class="origen-progress-container">
            <div class="origen-progress-bar">
                <div class="origen-progress-fill" id="progressBar"></div>
            </div>
            <div class="origen-step-indicator" id="stepIndicator">1 de 7</div>
        </div>

        <form id="origenEvaluationForm" novalidate>

            <!-- ============ PASO 1: Tipo de propiedad ============ -->
            <div class="origen-form-step active" data-step="1">
                <h2 class="origen-step-title">Tipo de propiedad</h2>
                <p class="origen-step-subtitle">Selecciona el tipo de propiedad donde se realizaría la instalación.</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="property_type" value="unifamiliar" required>
                        <span class="origen-option-icon">&#127968;</span>
                        <span class="origen-option-text">Vivienda unifamiliar</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="property_type" value="finca_aislada">
                        <span class="origen-option-icon">&#127806;</span>
                        <span class="origen-option-text">Vivienda o finca aislada</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="property_type" value="negocio_industria">
                        <span class="origen-option-icon">&#127981;</span>
                        <span class="origen-option-text">Negocio o Industria</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="property_type" value="comunidad_vecinos">
                        <span class="origen-option-icon">&#127970;</span>
                        <span class="origen-option-text">Comunidad de vecinos</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 2: Consumo eléctrico ============ -->
            <div class="origen-form-step" data-step="2">
                <h2 class="origen-step-title">Consumo eléctrico</h2>
                <p class="origen-step-subtitle">Indica tu consumo aproximado. Puedes verlo en kWh o en euros/mes.</p>

                <div class="origen-toggle-container">
                    <button type="button" class="consumption-toggle" data-mode="kwh">Ver en kWh</button>
                    <button type="button" class="consumption-toggle active" data-mode="euro">Ver en &euro;/mes</button>
                </div>

                <input type="hidden" name="consumption_type" id="consumptionType" value="euro">

                <!-- Opciones kWh -->
                <div class="origen-options-grid consumption-options" data-type="kwh" style="display:none;">
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="menos_200" required>
                        <span class="origen-option-icon">&#128161;</span>
                        <span class="origen-option-text">Menos de 200 kWh</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="200_500">
                        <span class="origen-option-icon">&#9889;</span>
                        <span class="origen-option-text">200 - 500 kWh</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="mas_500">
                        <span class="origen-option-icon">&#128267;</span>
                        <span class="origen-option-text">M&aacute;s de 500 kWh</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="otro_kwh">
                        <span class="origen-option-icon">&#128221;</span>
                        <span class="origen-option-text">Otro (especificar)</span>
                    </label>
                </div>
                <div id="otroConsumoKwh" class="origen-otro-input" style="display: none;">
                    <label>Indica tu consumo mensual en kWh:</label>
                    <input type="number" name="consumption_other_kwh" placeholder="Ej: 750" min="1">
                </div>

                <!-- Opciones Euros -->
                <div class="origen-options-grid consumption-options" data-type="euro">
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="50_150">
                        <span class="origen-option-icon">&#128181;</span>
                        <span class="origen-option-text">50 - 150&euro;/mes</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="150_300">
                        <span class="origen-option-icon">&#128182;</span>
                        <span class="origen-option-text">150 - 300&euro;/mes</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="mas_300">
                        <span class="origen-option-icon">&#128183;</span>
                        <span class="origen-option-text">M&aacute;s de 300&euro;/mes</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="consumption" value="otro_euros">
                        <span class="origen-option-icon">&#128176;</span>
                        <span class="origen-option-text">Otro (especificar)</span>
                    </label>
                </div>
                <div id="otroConsumoEuros" class="origen-otro-input" style="display: none;">
                    <label>Indica tu factura mensual en &euro;:</label>
                    <input type="number" name="consumption_other_euros" placeholder="Ej: 350" min="1">
                </div>
            </div>

            <!-- ============ PASO 3: Servicios de interés ============ -->
            <div class="origen-form-step" data-step="3">
                <h2 class="origen-step-title">Servicios de inter&eacute;s</h2>
                <p class="origen-step-subtitle">Selecciona los servicios que te interesan (puedes elegir varios).</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card origen-option-checkbox">
                        <input type="checkbox" name="services[]" value="fotovoltaica">
                        <span class="origen-option-icon">&#9728;&#65039;</span>
                        <span class="origen-option-text">Instalaci&oacute;n fotovoltaica</span>
                    </label>
                    <label class="origen-option-card origen-option-checkbox">
                        <input type="checkbox" name="services[]" value="punto_recarga">
                        <span class="origen-option-icon">&#128268;</span>
                        <span class="origen-option-text">Punto de recarga V.E.</span>
                    </label>
                    <label class="origen-option-card origen-option-checkbox">
                        <input type="checkbox" name="services[]" value="servicios_electricos">
                        <span class="origen-option-icon">&#9889;</span>
                        <span class="origen-option-text">Servicios el&eacute;ctricos</span>
                    </label>
                    <label class="origen-option-card origen-option-checkbox">
                        <input type="checkbox" name="services[]" value="mantenimiento">
                        <span class="origen-option-icon">&#128295;</span>
                        <span class="origen-option-text">Mantenimiento</span>
                    </label>
                    <label class="origen-option-card origen-option-checkbox">
                        <input type="checkbox" name="services[]" value="asesoria">
                        <span class="origen-option-icon">&#128161;</span>
                        <span class="origen-option-text">Asesor&iacute;a energ&eacute;tica</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 4: Plazo de instalación ============ -->
            <div class="origen-form-step" data-step="4">
                <h2 class="origen-step-title">Plazo de instalaci&oacute;n</h2>
                <p class="origen-step-subtitle">&iquest;Cu&aacute;ndo te gustar&iacute;a realizar la instalaci&oacute;n?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="timeframe" value="lo_antes_posible" required>
                        <span class="origen-option-icon">&#128640;</span>
                        <span class="origen-option-text">Lo antes posible</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="timeframe" value="3_6_meses">
                        <span class="origen-option-icon">&#128197;</span>
                        <span class="origen-option-text">En 3-6 meses</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="timeframe" value="6_12_meses">
                        <span class="origen-option-icon">&#128198;</span>
                        <span class="origen-option-text">En 6-12 meses</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 5: Ubicación ============ -->
            <div class="origen-form-step" data-step="5">
                <h2 class="origen-step-title">Ubicaci&oacute;n</h2>
                <p class="origen-step-subtitle">Ind&iacute;canos d&oacute;nde se encuentra la propiedad.</p>
                <div class="origen-input-group">
                    <label for="location">Municipio o zona de Tenerife <span class="required">*</span></label>
                    <input type="text" id="location" name="location" required placeholder="Ej: Santa Cruz, La Laguna, Adeje...">
                </div>
                <div class="origen-input-group">
                    <label for="additional_info">&iquest;Algo m&aacute;s que debamos saber? <span class="optional">(opcional)</span></label>
                    <textarea id="additional_info" name="additional_info" rows="3" placeholder="Cualquier informaci&oacute;n adicional que consideres relevante..."></textarea>
                </div>
            </div>

            <!-- ============ PASO 6: Datos de contacto ============ -->
            <div class="origen-form-step" data-step="6">
                <h2 class="origen-step-title">Datos de contacto</h2>
                <p class="origen-step-subtitle">Para poder enviarte tu evaluaci&oacute;n y contactar contigo.</p>
                <div class="origen-input-group">
                    <label for="name">Nombre completo <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required placeholder="Tu nombre completo">
                </div>
                <div class="origen-input-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="origen-input-group">
                    <label for="phone">Tel&eacute;fono <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required placeholder="Ej: 600 123 456">
                </div>
                <div class="origen-checkbox-group">
                    <label class="origen-checkbox-label">
                        <input type="checkbox" name="privacy_accepted" value="si" required>
                        <span>Acepto la <a href="/politica-de-privacidad/" target="_blank">pol&iacute;tica de privacidad</a> <span class="required">*</span></span>
                    </label>
                </div>
                <div class="origen-checkbox-group">
                    <label class="origen-checkbox-label">
                        <input type="checkbox" name="commercial_accepted" value="si">
                        <span>Deseo recibir informaci&oacute;n comercial</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 7: ¿Presupuesto inmediato? ============ -->
            <div class="origen-form-step" data-step="7">
                <h2 class="origen-step-title">&iquest;Quieres un presupuesto inmediato?</h2>
                <p class="origen-step-subtitle">Podemos calcular una estimaci&oacute;n ahora o contactarte directamente.</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="wants_quote" value="si" required>
                        <span class="origen-option-icon">&#128176;</span>
                        <span class="origen-option-text">S&iacute;, quiero presupuesto inmediato</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="wants_quote" value="no">
                        <span class="origen-option-icon">&#128222;</span>
                        <span class="origen-option-text">No, prefiero que me contacten</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 8: Tipo de techo ============ -->
            <div class="origen-form-step" data-step="8">
                <h2 class="origen-step-title">Tipo de techo</h2>
                <p class="origen-step-subtitle">&iquest;Qu&eacute; tipo de techo tiene tu propiedad?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="roof_type" value="teja" required>
                        <span class="origen-option-icon">&#127968;</span>
                        <span class="origen-option-text">Teja</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_type" value="chapa">
                        <span class="origen-option-icon">&#127959;</span>
                        <span class="origen-option-text">Chapa</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_type" value="azotea_plana">
                        <span class="origen-option-icon">&#127970;</span>
                        <span class="origen-option-text">Azotea plana</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_type" value="otro">
                        <span class="origen-option-icon">&#10067;</span>
                        <span class="origen-option-text">Otro</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 9: Orientación del techo ============ -->
            <div class="origen-form-step" data-step="9">
                <h2 class="origen-step-title">Orientaci&oacute;n del techo</h2>
                <p class="origen-step-subtitle">&iquest;Hacia d&oacute;nde est&aacute; orientado tu techo?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="roof_orientation" value="sur" required>
                        <span class="origen-option-icon">&#9728;&#65039;</span>
                        <span class="origen-option-text">Sur</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_orientation" value="sureste_suroeste">
                        <span class="origen-option-icon">&#127749;</span>
                        <span class="origen-option-text">Sureste / Suroeste</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_orientation" value="este_oeste">
                        <span class="origen-option-icon">&#127748;</span>
                        <span class="origen-option-text">Este / Oeste</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_orientation" value="norte_nosabe">
                        <span class="origen-option-icon">&#129517;</span>
                        <span class="origen-option-text">Norte / No lo s&eacute;</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 10: Superficie disponible ============ -->
            <div class="origen-form-step" data-step="10">
                <h2 class="origen-step-title">Superficie disponible</h2>
                <p class="origen-step-subtitle">&iquest;Cu&aacute;nta superficie tienes disponible para placas?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="roof_surface" value="menos_20" required>
                        <span class="origen-option-icon">&#128208;</span>
                        <span class="origen-option-text">Menos de 20 m&sup2; (2-4 placas)</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_surface" value="20_50">
                        <span class="origen-option-icon">&#128207;</span>
                        <span class="origen-option-text">20 - 50 m&sup2; (4-10 placas)</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_surface" value="mas_50">
                        <span class="origen-option-icon">&#128209;</span>
                        <span class="origen-option-text">M&aacute;s de 50 m&sup2; (+10 placas)</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_surface" value="no_seguro">
                        <span class="origen-option-icon">&#10068;</span>
                        <span class="origen-option-text">No estoy seguro</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="roof_surface" value="otro_superficie">
                        <span class="origen-option-icon">&#128207;</span>
                        <span class="origen-option-text">Otro (especificar)</span>
                    </label>
                </div>
                <div id="otroSuperficie" class="origen-otro-input" style="display: none;">
                    <label>Indica la superficie en m&sup2;:</label>
                    <input type="number" name="roof_surface_other" placeholder="Ej: 35" min="1">
                </div>
            </div>

            <!-- ============ PASO 11: Capacidad financiera ============ -->
            <div class="origen-form-step" data-step="11">
                <h2 class="origen-step-title">Capacidad financiera</h2>
                <p class="origen-step-subtitle">&iquest;C&oacute;mo planeas financiar la instalaci&oacute;n?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="financial_capacity" value="recursos_propios" required>
                        <span class="origen-option-icon">&#128181;</span>
                        <span class="origen-option-text">Recursos propios disponibles</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="financial_capacity" value="financiacion_preaprobada">
                        <span class="origen-option-icon">&#9989;</span>
                        <span class="origen-option-text">Financiaci&oacute;n pre-aprobada</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="financial_capacity" value="requiere_financiacion">
                        <span class="origen-option-icon">&#127974;</span>
                        <span class="origen-option-text">Requiere financiaci&oacute;n</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="financial_capacity" value="capacidad_incierta">
                        <span class="origen-option-icon">&#10069;</span>
                        <span class="origen-option-text">Capacidad incierta</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 12: Toma de decisión ============ -->
            <div class="origen-form-step" data-step="12">
                <h2 class="origen-step-title">Toma de decisi&oacute;n</h2>
                <p class="origen-step-subtitle">&iquest;Qui&eacute;n toma la decisi&oacute;n de la instalaci&oacute;n?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="decision_making" value="decisor_unico" required>
                        <span class="origen-option-icon">&#128100;</span>
                        <span class="origen-option-text">Decisor &uacute;nico</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="decision_making" value="decision_compartida">
                        <span class="origen-option-icon">&#128101;</span>
                        <span class="origen-option-text">Decisi&oacute;n compartida</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="decision_making" value="requiere_consenso">
                        <span class="origen-option-icon">&#128104;&#8205;&#128105;&#8205;&#128103;&#8205;&#128102;</span>
                        <span class="origen-option-text">Requiere consenso</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="decision_making" value="proceso_no_definido">
                        <span class="origen-option-icon">&#129335;</span>
                        <span class="origen-option-text">Proceso no definido</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 13: Motivación ============ -->
            <div class="origen-form-step" data-step="13">
                <h2 class="origen-step-title">Motivaci&oacute;n principal</h2>
                <p class="origen-step-subtitle">&iquest;Qu&eacute; te motiva a instalar energ&iacute;a solar?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="motivation" value="economica" required>
                        <span class="origen-option-icon">&#128176;</span>
                        <span class="origen-option-text">Econ&oacute;mica (ahorro en factura)</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="motivation" value="ecologica">
                        <span class="origen-option-icon">&#127793;</span>
                        <span class="origen-option-text">Ecol&oacute;gica</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="motivation" value="informativa">
                        <span class="origen-option-icon">&#8505;&#65039;</span>
                        <span class="origen-option-text">Informativa</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 14: Baterías ============ -->
            <div class="origen-form-step" data-step="14">
                <h2 class="origen-step-title">Bater&iacute;as de almacenamiento</h2>
                <p class="origen-step-subtitle">&iquest;Deseas a&ntilde;adir bater&iacute;as a tu instalaci&oacute;n?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="battery_option" value="none" required checked>
                        <span class="origen-option-icon">&#11093;</span>
                        <span class="origen-option-text">Sin bater&iacute;as</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="battery_option" value="5kwh">
                        <span class="origen-option-icon">&#128267;</span>
                        <span class="origen-option-text">Bater&iacute;a 5kWh<br><small class="origen-price-tag">+<span class="battery-price-5kwh">1.356,75</span>&euro;</small></span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="battery_option" value="10kwh">
                        <span class="origen-option-icon">&#128267;&#128267;</span>
                        <span class="origen-option-text">Bater&iacute;a 10kWh<br><small class="origen-price-tag">+<span class="battery-price-10kwh">2.451,15</span>&euro;</small></span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="battery_option" value="15kwh">
                        <span class="origen-option-icon">&#128267;&#128267;&#128267;</span>
                        <span class="origen-option-text">Bater&iacute;a 15kWh<br><small class="origen-price-tag">+<span class="battery-price-15kwh">3.536,55</span>&euro;</small></span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 15: Cargador Vehículo Eléctrico ============ -->
            <div class="origen-form-step" data-step="15">
                <h2 class="origen-step-title">Cargador Veh&iacute;culo El&eacute;ctrico</h2>
                <p class="origen-step-subtitle">&iquest;Deseas a&ntilde;adir un punto de recarga para tu veh&iacute;culo el&eacute;ctrico?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="ve_charger" value="no" required checked>
                        <span class="origen-option-icon">&#10060;</span>
                        <span class="origen-option-text">Sin cargador</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="ve_charger" value="si">
                        <span class="origen-option-icon">&#128268;</span>
                        <span class="origen-option-text">Con cargador V.E.<br><small class="origen-price-tag">+<span class="ve-charger-price">995</span>&euro;</small></span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 16: Preferencia de contacto ============ -->
            <div class="origen-form-step" data-step="16">
                <h2 class="origen-step-title">&Uacute;ltimo paso antes de ver tu presupuesto</h2>
                <p class="origen-step-subtitle">Para darte un presupuesto m&aacute;s preciso y personalizado, &iquest;cu&aacute;ndo te viene bien que te contactemos?</p>
                <div class="origen-options-grid">
                    <label class="origen-option-card">
                        <input type="radio" name="contact_preference" value="manana" required>
                        <span class="origen-option-icon">&#127749;</span>
                        <span class="origen-option-text">Ma&ntilde;ana</span>
                        <span class="origen-option-subtext">9h - 14h</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="contact_preference" value="tarde">
                        <span class="origen-option-icon">&#127751;</span>
                        <span class="origen-option-text">Tarde</span>
                        <span class="origen-option-subtext">14h - 19h</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="contact_preference" value="cualquier_hora">
                        <span class="origen-option-icon">&#9200;</span>
                        <span class="origen-option-text">Cualquier hora</span>
                        <span class="origen-option-subtext">Flexible</span>
                    </label>
                    <label class="origen-option-card">
                        <input type="radio" name="contact_preference" value="solo_email">
                        <span class="origen-option-icon">&#128231;</span>
                        <span class="origen-option-text">Solo email</span>
                        <span class="origen-option-subtext">No llamar</span>
                    </label>
                </div>
            </div>

            <!-- ============ PASO 17: Presupuesto calculado ============ -->
            <div class="origen-form-step" data-step="17">
                <h2 class="origen-step-title">Tu presupuesto estimado</h2>
                <p class="origen-step-subtitle">Basado en tus respuestas, esta es nuestra recomendaci&oacute;n.</p>

                <div class="origen-budget-card">
                    <div class="origen-budget-header">
                        <span class="origen-budget-icon">&#9728;&#65039;</span>
                        <h3>Instalaci&oacute;n Recomendada</h3>
                    </div>
                    <div class="origen-budget-details">
                        <div class="origen-budget-row">
                            <span class="origen-budget-label">Potencia:</span>
                            <span class="origen-budget-value" id="recommendedPower">-</span>
                        </div>
                        <div class="origen-budget-row">
                            <span class="origen-budget-label">N&uacute;mero de placas:</span>
                            <span class="origen-budget-value" id="recommendedPanels">-</span>
                        </div>
                        <div class="origen-budget-row">
                            <span class="origen-budget-label">Precio base instalaci&oacute;n:</span>
                            <span class="origen-budget-value" id="basePrice">-</span>
                        </div>
                        <div class="origen-budget-row origen-budget-extras" id="extrasRow" style="display:none;">
                            <span class="origen-budget-label">Extras:</span>
                            <span class="origen-budget-value" id="extrasDetail">-</span>
                        </div>
                        <div class="origen-budget-row origen-budget-total">
                            <span class="origen-budget-label">PRECIO TOTAL:</span>
                            <span class="origen-budget-value" id="totalPrice">-</span>
                        </div>
                    </div>
                    <div class="origen-budget-savings">
                        <div class="origen-budget-row">
                            <span class="origen-budget-label">Ahorro anual estimado:</span>
                            <span class="origen-budget-value origen-savings-value" id="annualSavings">-</span>
                        </div>
                        <div class="origen-budget-row">
                            <span class="origen-budget-label">Per&iacute;odo de amortizaci&oacute;n:</span>
                            <span class="origen-budget-value origen-savings-value" id="paybackYears">-</span>
                        </div>
                    </div>
                </div>

                <div class="origen-disclaimer" id="disclaimerBox">
                    <p id="disclaimerText">Esta es una propuesta aproximada y ficticia, sujeta a contacto directo, evaluaci&oacute;n de necesidades reales y visita t&eacute;cnica para presupuesto definitivo.</p>
                </div>

                <!-- Anti-duplicado -->
                <input type="hidden" name="submission_uid" id="submissionUid">
                <!-- Campos ocultos para datos calculados -->
                <input type="hidden" name="calc_adjustment_reason" id="calcAdjustmentReason">
                <input type="hidden" name="calc_recommended_power" id="calcRecommendedPower">
                <input type="hidden" name="calc_recommended_panels" id="calcRecommendedPanels">
                <input type="hidden" name="calc_base_price" id="calcBasePrice">
                <input type="hidden" name="calc_battery_price" id="calcBatteryPrice">
                <input type="hidden" name="calc_ve_price" id="calcVePrice">
                <input type="hidden" name="calc_total_price" id="calcTotalPrice">
                <input type="hidden" name="calc_annual_savings" id="calcAnnualSavings">
                <input type="hidden" name="calc_payback_years" id="calcPaybackYears">
            </div>

            <!-- ============ MENSAJE DE ÉXITO ============ -->
            <div id="successMessage" class="origen-success-message" style="display:none;">
                <div class="origen-success-icon">&#10003;</div>
                <h2>&iexcl;Solicitud enviada con &eacute;xito!</h2>
                <p>Hemos recibido tu evaluaci&oacute;n energ&eacute;tica. Nuestro equipo se pondr&aacute; en contacto contigo a la mayor brevedad.</p>
                <p class="origen-success-contact">
                    &#128231; <a href="mailto:info@origensostenible.net">info@origensostenible.net</a><br>
                    &#128222; <a href="tel:+34607445541">607 44 55 41</a><br>
                    &#128172; <a href="https://wa.me/34607445541" target="_blank">WhatsApp: 607 44 55 41</a>
                </p>
                <a href="/" class="origen-btn-primary" style="display: inline-block; margin-top: 30px; text-decoration: none;">&larr; Volver al inicio</a>
            </div>

            <!-- ============ BOTONES NAVEGACIÓN ============ -->
            <div class="origen-nav-buttons" id="navButtons">
                <button type="button" class="origen-btn-secondary" id="btnPrev" style="display:none;">Anterior</button>
                <button type="button" class="origen-btn-primary" id="btnNext">Siguiente</button>
                <button type="submit" class="origen-btn-primary" id="btnSubmit" style="display:none;">Enviar solicitud</button>
            </div>

            <!-- Spinner de carga -->
            <div id="origenSpinner" class="origen-spinner" style="display:none;">
                <div class="origen-spinner-circle"></div>
                <p>Enviando tu solicitud...</p>
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// =============================================================================
// AJAX: Procesar envío del formulario
// =============================================================================

add_action('wp_ajax_origen_submit_form', 'origen_process_form');
add_action('wp_ajax_nopriv_origen_submit_form', 'origen_process_form');

function origen_process_form() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'origen_form_nonce')) {
        wp_send_json_error(array('message' => 'Error de seguridad. Recarga la página e inténtalo de nuevo.'));
    }

    // Anti-duplicado: verificar submission_uid
    $submission_uid = sanitize_text_field(wp_unslash($_POST['submission_uid'] ?? ''));
    if (!empty($submission_uid)) {
        $transient_key = 'origen_sub_' . md5($submission_uid);
        if (get_transient($transient_key)) {
            wp_send_json_error(array('message' => 'Esta solicitud ya fue enviada.'));
        }
        set_transient($transient_key, true, 300);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'origen_form_submissions';

    // Sanitizar datos
    $data = array(
        'property_type'      => sanitize_text_field(wp_unslash($_POST['property_type'] ?? '')),
        'consumption_type'   => sanitize_text_field(wp_unslash($_POST['consumption_type'] ?? 'kwh')),
        'consumption_value'  => sanitize_text_field(wp_unslash($_POST['consumption'] ?? '')),
        'consumption_other_kwh' => sanitize_text_field(wp_unslash($_POST['consumption_other_kwh'] ?? '')),
        'consumption_other_euros' => sanitize_text_field(wp_unslash($_POST['consumption_other_euros'] ?? '')),
        'services'           => '',
        'timeframe'          => sanitize_text_field(wp_unslash($_POST['timeframe'] ?? '')),
        'location'           => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
        'additional_info'    => sanitize_textarea_field(wp_unslash($_POST['additional_info'] ?? '')),
        'name'               => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
        'email'              => sanitize_email(wp_unslash($_POST['email'] ?? '')),
        'phone'              => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
        'privacy_accepted'   => sanitize_text_field(wp_unslash($_POST['privacy_accepted'] ?? 'no')),
        'commercial_accepted'=> sanitize_text_field(wp_unslash($_POST['commercial_accepted'] ?? 'no')),
        'wants_quote'        => sanitize_text_field(wp_unslash($_POST['wants_quote'] ?? 'no')),
        'roof_type'          => sanitize_text_field(wp_unslash($_POST['roof_type'] ?? '')),
        'roof_orientation'   => sanitize_text_field(wp_unslash($_POST['roof_orientation'] ?? '')),
        'roof_surface'       => sanitize_text_field(wp_unslash($_POST['roof_surface'] ?? '')),
        'roof_surface_other' => sanitize_text_field(wp_unslash($_POST['roof_surface_other'] ?? '')),
        'financial_capacity' => sanitize_text_field(wp_unslash($_POST['financial_capacity'] ?? '')),
        'decision_making'    => sanitize_text_field(wp_unslash($_POST['decision_making'] ?? '')),
        'motivation'         => sanitize_text_field(wp_unslash($_POST['motivation'] ?? '')),
        'battery_option'     => sanitize_text_field(wp_unslash($_POST['battery_option'] ?? 'none')),
        'wants_ve_charger'   => sanitize_text_field(wp_unslash($_POST['ve_charger'] ?? 'no')),
        'contact_preference' => sanitize_text_field(wp_unslash($_POST['contact_preference'] ?? '')),
        'adjustment_reason'  => sanitize_text_field(wp_unslash($_POST['calc_adjustment_reason'] ?? '')),
        'recommended_power'  => sanitize_text_field(wp_unslash($_POST['calc_recommended_power'] ?? '')),
        'recommended_panels' => intval($_POST['calc_recommended_panels'] ?? 0),
        'base_price'         => floatval($_POST['calc_base_price'] ?? 0),
        'battery_price'      => floatval($_POST['calc_battery_price'] ?? 0),
        've_price'           => floatval($_POST['calc_ve_price'] ?? 0),
        'total_price'        => floatval($_POST['calc_total_price'] ?? 0),
        'annual_savings'     => floatval($_POST['calc_annual_savings'] ?? 0),
        'payback_years'      => floatval($_POST['calc_payback_years'] ?? 0),
        'ip_address'         => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
        'user_agent'         => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'submitted_at'       => current_time('mysql'),
    );

    // Procesar servicios (array de checkboxes)
    if (isset($_POST['services']) && is_array($_POST['services'])) {
        $services = array_map('sanitize_text_field', wp_unslash($_POST['services']));
        $data['services'] = implode(', ', $services);
    }

    // Validaciones básicas
    if (empty($data['name']) || empty($data['email']) || empty($data['phone'])) {
        wp_send_json_error(array('message' => 'Por favor, completa todos los campos obligatorios.'));
    }

    if (!is_email($data['email'])) {
        wp_send_json_error(array('message' => 'El email proporcionado no es válido.'));
    }

    if ($data['privacy_accepted'] !== 'si') {
        wp_send_json_error(array('message' => 'Debes aceptar la política de privacidad.'));
    }

    // Insertar en base de datos
    $inserted = $wpdb->insert($table_name, $data);

    if ($inserted === false) {
        wp_send_json_error(array('message' => 'Error al guardar los datos. Inténtalo de nuevo.'));
    }

    $submission_id = $wpdb->insert_id;

    // Enviar emails
    origen_send_user_email($data, $submission_id);
    origen_send_admin_email($data, $submission_id);

    wp_send_json_success(array(
        'message' => '¡Solicitud enviada correctamente!',
        'id'      => $submission_id,
    ));
}

// =============================================================================
// EMAILS
// =============================================================================

function origen_get_label($field, $value) {
    $labels = array(
        'property_type' => array(
            'unifamiliar'       => 'Vivienda unifamiliar',
            'finca_aislada'     => 'Vivienda o finca aislada',
            'negocio_industria' => 'Negocio o Industria',
            'comunidad_vecinos' => 'Comunidad de vecinos',
        ),
        'consumption' => array(
            'menos_200'  => 'Menos de 200 kWh',
            '200_500'    => '200 - 500 kWh',
            'mas_500'    => 'Más de 500 kWh',
            'otro_kwh'   => 'Personalizado (kWh)',
            '50_150'     => '50 - 150€/mes',
            '150_300'    => '150 - 300€/mes',
            'mas_300'    => 'Más de 300€/mes',
            'otro_euros' => 'Personalizado (€/mes)',
        ),
        'timeframe' => array(
            'lo_antes_posible' => 'Lo antes posible',
            '3_6_meses'        => 'En 3-6 meses',
            '6_12_meses'       => 'En 6-12 meses',
        ),
        'roof_type' => array(
            'teja'         => 'Teja',
            'chapa'        => 'Chapa',
            'azotea_plana' => 'Azotea plana',
            'otro'         => 'Otro',
        ),
        'roof_orientation' => array(
            'sur'              => 'Sur',
            'sureste_suroeste' => 'Sureste / Suroeste',
            'este_oeste'       => 'Este / Oeste',
            'norte_nosabe'     => 'Norte / No lo sé',
        ),
        'roof_surface' => array(
            'menos_20'         => 'Menos de 20 m²',
            '20_50'            => '20 - 50 m²',
            'mas_50'           => 'Más de 50 m²',
            'no_seguro'        => 'No estoy seguro',
            'otro_superficie'  => 'Personalizado (m²)',
        ),
        'financial_capacity' => array(
            'recursos_propios'         => 'Recursos propios disponibles',
            'financiacion_preaprobada' => 'Financiación pre-aprobada',
            'requiere_financiacion'    => 'Requiere financiación',
            'capacidad_incierta'       => 'Capacidad incierta',
        ),
        'decision_making' => array(
            'decisor_unico'      => 'Decisor único',
            'decision_compartida'=> 'Decisión compartida',
            'requiere_consenso'  => 'Requiere consenso',
            'proceso_no_definido'=> 'Proceso no definido',
        ),
        'motivation' => array(
            'economica'   => 'Económica (ahorro en factura)',
            'ecologica'   => 'Ecológica',
            'informativa' => 'Informativa',
        ),
        'battery_option' => array(
            'none'  => 'Sin baterías',
            '5kwh'  => 'Batería 5kWh',
            '10kwh' => 'Batería 10kWh',
            '15kwh' => 'Batería 15kWh',
        ),
        'contact_preference' => array(
            'manana'         => 'Mañana (9h-14h)',
            'tarde'          => 'Tarde (14h-19h)',
            'cualquier_hora' => 'Cualquier hora',
            'solo_email'     => 'Solo email (no llamar)',
        ),
        'adjustment_reason' => array(
            'kwh'        => 'Ajustado a consumo en kWh',
            'euros'      => 'Ajustado a consumo en €/mes',
            'superficie' => 'Ajustado a superficie disponible',
        ),
    );

    if (isset($labels[$field][$value])) {
        return $labels[$field][$value];
    }
    return $value;
}

function origen_get_disclaimer_text($adjustment_reason) {
    $disclaimer_texts = array(
        'kwh'        => 'Esta es una propuesta aproximada y ficticia ajustada a su consumo en kWh',
        'euros'      => 'Esta es una propuesta aproximada y ficticia ajustada a su consumo en &euro;/mes',
        'superficie' => 'Esta es una propuesta aproximada y ficticia ajustada a su superficie disponible',
    );
    $base = isset($disclaimer_texts[$adjustment_reason]) ? $disclaimer_texts[$adjustment_reason] : 'Esta es una propuesta aproximada y ficticia';
    return $base . ', sujeta a contacto directo, evaluaci&oacute;n de necesidades reales y visita t&eacute;cnica para presupuesto definitivo.';
}

function origen_send_user_email($data, $submission_id) {
    $name = esc_html($data['name']);
    $wants_quote = $data['wants_quote'] === 'si';
    $adjustment_reason = isset($data['adjustment_reason']) ? $data['adjustment_reason'] : '';

    $budget_section = '';
    if ($wants_quote && floatval($data['total_price']) > 0) {
        $disclaimer = origen_get_disclaimer_text($adjustment_reason);
        $budget_section = '
        <tr><td colspan="2" style="padding: 20px 0 10px 0;"><h2 style="color: #00AA9F; margin: 0; font-size: 20px;">Tu Presupuesto Estimado</h2></td></tr>
        <tr style="background: #f0faf9;"><td style="padding: 12px; font-weight: 600;">Instalaci&oacute;n recomendada:</td><td style="padding: 12px;">' . esc_html($data['recommended_power']) . ' kW (' . intval($data['recommended_panels']) . ' placas)</td></tr>
        <tr><td style="padding: 12px; font-weight: 600;">Precio base:</td><td style="padding: 12px;">' . number_format(floatval($data['base_price']), 2, ',', '.') . '&euro;</td></tr>
        <tr style="background: #f0faf9;"><td style="padding: 12px; font-weight: 600;">Bater&iacute;as:</td><td style="padding: 12px;">' . (floatval($data['battery_price']) > 0 ? number_format(floatval($data['battery_price']), 2, ',', '.') . '&euro;' : 'No incluidas') . '</td></tr>
        <tr><td style="padding: 12px; font-weight: 600;">Cargador V.E.:</td><td style="padding: 12px;">' . (floatval($data['ve_price']) > 0 ? number_format(floatval($data['ve_price']), 2, ',', '.') . '&euro;' : 'No incluido') . '</td></tr>
        <tr style="background: #00AA9F; color: white;"><td style="padding: 15px; font-weight: 700; font-size: 16px;">PRECIO TOTAL:</td><td style="padding: 15px; font-weight: 700; font-size: 18px;">' . number_format(floatval($data['total_price']), 2, ',', '.') . '&euro;</td></tr>
        <tr style="background: #f0faf9;"><td style="padding: 12px; font-weight: 600;">Ahorro anual estimado:</td><td style="padding: 12px; color: #00AA9F; font-weight: 600;">' . number_format(floatval($data['annual_savings']), 2, ',', '.') . '&euro;/a&ntilde;o</td></tr>
        <tr><td style="padding: 12px; font-weight: 600;">Per&iacute;odo de amortizaci&oacute;n:</td><td style="padding: 12px;">' . number_format(floatval($data['payback_years']), 1, ',', '.') . ' a&ntilde;os</td></tr>
        <tr><td colspan="2" style="padding: 15px; background: #FFF3E0; border-left: 4px solid #F39322; font-size: 13px; color: #666;">
            ' . $disclaimer . '
        </td></tr>';
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, Helvetica, sans-serif; background: #F9F9F7; margin: 0; padding: 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <tr>
                <td style="background: linear-gradient(135deg, #00AA9F 0%, #008A82 100%); padding: 30px; text-align: center;">
                    <img src="https://www.origensostenible.net/wp-content/uploads/2026/02/Logo_blanco_v5.png" alt="Origen Sostenible" style="max-width: 180px; height: auto; margin-bottom: 15px;">
                    <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">Evaluaci&oacute;n Energ&eacute;tica</p>
                </td>
            </tr>
            <tr>
                <td style="padding: 30px;">
                    <p style="font-size: 16px; color: #333;">Hola <strong>' . $name . '</strong>,</p>
                    <p style="color: #666;">Gracias por completar nuestra evaluación energética. Hemos recibido tu solicitud correctamente.</p>

                    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E0E0E0; border-radius: 8px; overflow: hidden; margin: 20px 0;">
                        <tr><td colspan="2" style="padding: 15px; background: #00AA9F; color: white; font-weight: 600;">Resumen de tu solicitud</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600; width: 40%;">Tipo de propiedad:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('property_type', $data['property_type'])) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Consumo:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('consumption', $data['consumption_value'])) . '</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Servicios:</td><td style="padding: 10px 12px;">' . esc_html($data['services']) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Plazo:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('timeframe', $data['timeframe'])) . '</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Ubicación:</td><td style="padding: 10px 12px;">' . esc_html($data['location']) . '</td></tr>
                        ' . $budget_section . '
                    </table>

                    <p style="color: #666;">Nuestro equipo se pondrá en contacto contigo a la mayor brevedad para asesorarte de forma personalizada.</p>

                    <table width="100%" cellpadding="0" cellspacing="0" style="background: #F9F9F7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <tr><td style="padding: 20px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-weight: 600; color: #333;">Contacta con nosotros:</p>
                            <p style="margin: 5px 0;"><a href="mailto:info@origensostenible.net" style="color: #00AA9F; text-decoration: none;">&#128231; info@origensostenible.net</a></p>
                            <p style="margin: 5px 0;"><a href="tel:+34607445541" style="color: #00AA9F; text-decoration: none;">&#128222; 607 44 55 41</a></p>
                            <p style="margin: 5px 0;"><a href="https://wa.me/34607445541" style="color: #00AA9F; text-decoration: none;">&#128172; WhatsApp: 607 44 55 41</a></p>
                            <p style="margin: 5px 0;"><a href="https://www.origensostenible.net" style="color: #00AA9F; text-decoration: none;">&#127760; www.origensostenible.net</a></p>
                        </td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="background: #333; padding: 20px; text-align: center;">
                    <p style="color: #999; margin: 0; font-size: 12px;">Origen Sostenible SL &bull; Tenerife</p>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Origen Sostenible <info@origensostenible.net>',
    );

    wp_mail($data['email'], 'Tu evaluación energética - Origen Sostenible', $html, $headers);
}

function origen_send_admin_email($data, $submission_id) {
    $wants_quote = $data['wants_quote'] === 'si';
    $admin_url = admin_url('admin.php?page=origen-form&action=view&id=' . intval($submission_id));
    $adjustment_reason = isset($data['adjustment_reason']) ? $data['adjustment_reason'] : '';

    // Preferencia de contacto
    $contact_pref = isset($data['contact_preference']) ? $data['contact_preference'] : '';
    $contact_pref_label = origen_get_label('contact_preference', $contact_pref);

    $budget_section = '';
    if ($wants_quote && floatval($data['total_price']) > 0) {
        $disclaimer = origen_get_disclaimer_text($adjustment_reason);
        $budget_section = '
        <tr><td colspan="2" style="padding: 20px 0 10px 0;"><h2 style="color: #F39322; margin: 0; font-size: 18px; border-bottom: 2px solid #F39322; padding-bottom: 5px;">PRESUPUESTO CALCULADO</h2></td></tr>
        <tr style="background: #FFF3E0;"><td style="padding: 10px 12px; font-weight: 600;">Instalaci&oacute;n:</td><td style="padding: 10px 12px;">' . esc_html($data['recommended_power']) . ' kW (' . intval($data['recommended_panels']) . ' placas)</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Precio base:</td><td style="padding: 10px 12px;">' . number_format(floatval($data['base_price']), 2, ',', '.') . '&euro;</td></tr>
        <tr style="background: #FFF3E0;"><td style="padding: 10px 12px; font-weight: 600;">Bater&iacute;as:</td><td style="padding: 10px 12px;">' . number_format(floatval($data['battery_price']), 2, ',', '.') . '&euro; (' . esc_html(origen_get_label('battery_option', $data['battery_option'])) . ')</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Cargador V.E.:</td><td style="padding: 10px 12px;">' . number_format(floatval($data['ve_price']), 2, ',', '.') . '&euro;</td></tr>
        <tr style="background: #00AA9F; color: white;"><td style="padding: 12px; font-weight: 700;">PRECIO TOTAL:</td><td style="padding: 12px; font-weight: 700; font-size: 18px;">' . number_format(floatval($data['total_price']), 2, ',', '.') . '&euro;</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Ahorro anual:</td><td style="padding: 10px 12px; color: #00AA9F;">' . number_format(floatval($data['annual_savings']), 2, ',', '.') . '&euro;/a&ntilde;o</td></tr>
        <tr style="background: #FFF3E0;"><td style="padding: 10px 12px; font-weight: 600;">Amortizaci&oacute;n:</td><td style="padding: 10px 12px;">' . number_format(floatval($data['payback_years']), 1, ',', '.') . ' a&ntilde;os</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Criterio ajuste:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('adjustment_reason', $adjustment_reason)) . '</td></tr>

        <tr><td colspan="2" style="padding: 10px 12px;"><strong>Detalle t&eacute;cnico:</strong></td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Tipo techo:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('roof_type', $data['roof_type'])) . '</td></tr>
        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Orientaci&oacute;n:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('roof_orientation', $data['roof_orientation'])) . '</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Superficie:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('roof_surface', $data['roof_surface'])) . (!empty($data['roof_surface_other']) ? ' (' . esc_html($data['roof_surface_other']) . ' m&sup2;)' : '') . '</td></tr>
        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Cap. financiera:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('financial_capacity', $data['financial_capacity'])) . '</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Toma decisi&oacute;n:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('decision_making', $data['decision_making'])) . '</td></tr>
        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Motivaci&oacute;n:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('motivation', $data['motivation'])) . '</td></tr>
        <tr><td style="padding: 10px 12px; font-weight: 600;">Pref. contacto:</td><td style="padding: 10px 12px; font-weight: 700; color: #F39322;">' . esc_html($contact_pref_label) . '</td></tr>
        <tr><td colspan="2" style="padding: 15px; background: #FFF3E0; border-left: 4px solid #F39322; font-size: 13px; color: #666;">
            ' . $disclaimer . '
        </td></tr>';
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, Helvetica, sans-serif; background: #F9F9F7; margin: 0; padding: 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <tr>
                <td style="background: linear-gradient(135deg, #F39322 0%, #e07d0a 100%); padding: 25px; text-align: center;">
                    <img src="https://www.origensostenible.net/wp-content/uploads/2026/02/Logo_blanco_v5.png" alt="Origen Sostenible" style="max-width: 150px; height: auto; margin-bottom: 10px;">
                    <h1 style="color: white; margin: 0; font-size: 20px;">Nueva Solicitud #' . intval($submission_id) . '</h1>
                    <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">Formulario de Evaluaci&oacute;n Energ&eacute;tica</p>
                </td>
            </tr>
            <tr>
                <td style="padding: 25px;">
                    <p style="margin: 0 0 15px 0;"><a href="' . esc_url($admin_url) . '" style="background: #00AA9F; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Ver en el panel de administración</a></p>

                    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E0E0E0; border-radius: 8px; overflow: hidden; margin: 20px 0;">
                        <tr><td colspan="2" style="padding: 12px; background: #333; color: white; font-weight: 600;">Datos del contacto</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600; width: 35%;">Nombre:</td><td style="padding: 10px 12px;">' . esc_html($data['name']) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Email:</td><td style="padding: 10px 12px;"><a href="mailto:' . esc_attr($data['email']) . '">' . esc_html($data['email']) . '</a></td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Teléfono:</td><td style="padding: 10px 12px;"><a href="tel:' . esc_attr($data['phone']) . '">' . esc_html($data['phone']) . '</a></td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Info comercial:</td><td style="padding: 10px 12px;">' . ($data['commercial_accepted'] === 'si' ? 'Sí, acepta' : 'No') . '</td></tr>

                        <tr><td colspan="2" style="padding: 15px 0 5px 0;"><h3 style="margin: 0; padding: 0 12px; color: #333;">Datos del formulario</h3></td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Propiedad:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('property_type', $data['property_type'])) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Consumo:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('consumption', $data['consumption_value'])) . ' (' . esc_html($data['consumption_type']) . ')</td></tr>
                        ' . (!empty($data['consumption_other_kwh']) ? '<tr style="background: #f0faf9;"><td style="padding: 10px 12px; font-weight: 600;">Consumo personalizado:</td><td style="padding: 10px 12px; color: #00AA9F; font-weight: 600;">' . esc_html($data['consumption_other_kwh']) . ' kWh/mes</td></tr>' : '') . '
                        ' . (!empty($data['consumption_other_euros']) ? '<tr style="background: #f0faf9;"><td style="padding: 10px 12px; font-weight: 600;">Consumo personalizado:</td><td style="padding: 10px 12px; color: #00AA9F; font-weight: 600;">' . esc_html($data['consumption_other_euros']) . ' &euro;/mes</td></tr>' : '') . '
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Servicios:</td><td style="padding: 10px 12px;">' . esc_html($data['services']) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Plazo:</td><td style="padding: 10px 12px;">' . esc_html(origen_get_label('timeframe', $data['timeframe'])) . '</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Ubicación:</td><td style="padding: 10px 12px;">' . esc_html($data['location']) . '</td></tr>
                        <tr><td style="padding: 10px 12px; font-weight: 600;">Info adicional:</td><td style="padding: 10px 12px;">' . esc_html($data['additional_info'] ?: 'N/A') . '</td></tr>
                        <tr style="background: #f9f9f9;"><td style="padding: 10px 12px; font-weight: 600;">Presupuesto:</td><td style="padding: 10px 12px; font-weight: 700; color: ' . ($wants_quote ? '#00AA9F' : '#F39322') . ';">' . ($wants_quote ? 'S&Iacute;, solicitado' : 'No, prefiere contacto') . '</td></tr>
                        ' . ($contact_pref ? '<tr><td style="padding: 10px 12px; font-weight: 600;">Pref. contacto:</td><td style="padding: 10px 12px; font-weight: 700; color: #F39322;">' . esc_html($contact_pref_label) . '</td></tr>' : '') . '

                        ' . $budget_section . '
                    </table>

                    <p style="color: #999; font-size: 12px;">IP: ' . esc_html($data['ip_address']) . ' | Fecha: ' . esc_html($data['submitted_at']) . '</p>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Origen Sostenible <info@origensostenible.net>',
    );

    $subject = 'Nueva solicitud #' . intval($submission_id) . ' - Origen Sostenible';

    // Enviar al admin
    wp_mail(get_option('admin_email'), $subject, $html, $headers);

    // Enviar al comercial
    wp_mail('comercial@origensostenible.net', $subject, $html, $headers);
}

// =============================================================================
// MENÚ DE ADMINISTRACIÓN
// =============================================================================

add_action('admin_menu', 'origen_admin_menu');

function origen_admin_menu() {
    add_menu_page(
        'Formularios Origen',
        'Formularios Origen',
        'manage_options',
        'origen-form',
        'origen_admin_page_render',
        'dashicons-analytics',
        30
    );

    add_submenu_page(
        'origen-form',
        'Ver formularios',
        'Ver formularios',
        'manage_options',
        'origen-form',
        'origen_admin_page_render'
    );

    add_submenu_page(
        'origen-form',
        'Exportar CSV',
        'Exportar CSV',
        'manage_options',
        'origen-form-export',
        'origen_export_page_render'
    );

    add_submenu_page(
        'origen-form',
        'Configuración de Precios',
        'Precios',
        'manage_options',
        'origen-form-pricing',
        'origen_pricing_page_render'
    );
}

function origen_admin_page_render() {
    require_once ORIGEN_FORM_PATH . 'templates/admin-page.php';
}

function origen_export_page_render() {
    require_once ORIGEN_FORM_PATH . 'templates/export-page.php';
}

function origen_pricing_page_render() {
    require_once ORIGEN_FORM_PATH . 'templates/pricing-settings.php';
}

// =============================================================================
// EXPORTAR CSV (AJAX)
// =============================================================================

add_action('admin_init', 'origen_handle_csv_export');

function origen_handle_csv_export() {
    if (!isset($_GET['origen_export_csv']) || $_GET['origen_export_csv'] !== '1') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'origen_export_csv')) {
        wp_die('Error de seguridad.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'origen_form_submissions';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC", ARRAY_A);

    // UTF-8 BOM
    $bom = "\xEF\xBB\xBF";

    $filename = 'origen-formularios-' . gmdate('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, $bom);

    // Cabeceras
    $headers = array(
        'ID', 'Tipo propiedad', 'Tipo consumo', 'Valor consumo',
        'Consumo personalizado kWh', 'Consumo personalizado €',
        'Servicios', 'Plazo', 'Ubicación', 'Info adicional',
        'Nombre', 'Email', 'Teléfono', 'Privacidad', 'Comercial',
        'Quiere presupuesto', 'Tipo techo', 'Orientación', 'Superficie',
        'Superficie personalizada m²', 'Cap. financiera',
        'Toma decisión', 'Motivación', 'Baterías', 'Cargador VE',
        'Pref. contacto', 'Criterio ajuste',
        'Potencia recomendada', 'Placas recomendadas', 'Precio base',
        'Precio baterías', 'Precio VE', 'Precio total',
        'Ahorro anual', 'Años amortización', 'IP', 'User Agent', 'Fecha'
    );

    fputcsv($output, $headers, ';');

    if ($results) {
        foreach ($results as $row) {
            fputcsv($output, array_values($row), ';');
        }
    }

    fclose($output);
    exit;
}

// =============================================================================
// GUARDAR PRECIOS (Admin)
// =============================================================================

add_action('admin_init', 'origen_save_pricing');

function origen_save_pricing() {
    if (!isset($_POST['origen_save_prices']) || $_POST['origen_save_prices'] !== '1') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos.');
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'origen_save_prices')) {
        wp_die('Error de seguridad.');
    }

    // Instalaciones
    $powers = array(2, 3, 4, 5, 6, 8, 10);
    $installations = array();
    foreach ($powers as $power) {
        $key = str_replace('.', '_', (string) $power);
        $panels = isset($_POST['panels_' . $key]) ? intval($_POST['panels_' . $key]) : 0;
        $price  = isset($_POST['price_' . $key]) ? floatval($_POST['price_' . $key]) : 0;
        $installations[] = array(
            'power'  => $power,
            'panels' => $panels,
            'price'  => $price,
        );
    }
    update_option('origen_installation_prices', $installations);

    // Baterías
    $batteries = array(
        '5kwh'  => isset($_POST['battery_5kwh']) ? floatval($_POST['battery_5kwh']) : 0,
        '10kwh' => isset($_POST['battery_10kwh']) ? floatval($_POST['battery_10kwh']) : 0,
        '15kwh' => isset($_POST['battery_15kwh']) ? floatval($_POST['battery_15kwh']) : 0,
    );
    update_option('origen_battery_prices', $batteries);

    // Cargador VE
    $ve_price = isset($_POST['ve_charger_price']) ? floatval($_POST['ve_charger_price']) : 0;
    update_option('origen_ve_charger_price', $ve_price);

    // Parámetros técnicos
    if (isset($_POST['origen_sqm_per_panel'])) {
        update_option('origen_sqm_per_panel', floatval($_POST['origen_sqm_per_panel']));
    }
    if (isset($_POST['origen_hsp_hours'])) {
        update_option('origen_hsp_hours', floatval($_POST['origen_hsp_hours']));
    }
    if (isset($_POST['origen_electricity_price'])) {
        update_option('origen_electricity_price', floatval($_POST['origen_electricity_price']));
    }
    if (isset($_POST['origen_watts_per_panel'])) {
        update_option('origen_watts_per_panel', intval($_POST['origen_watts_per_panel']));
    }

    add_settings_error('origen_prices', 'prices_updated', 'Precios y parámetros actualizados correctamente.', 'updated');
}
