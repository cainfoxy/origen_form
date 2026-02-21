<?php
/**
 * Clase para cálculos de presupuesto - Origen Sostenible v1.5.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Origen_Budget_Calculator {

    private $installations;
    private $batteries;
    private $ve_charger_price;

    // Parámetros técnicos editables
    private $sqm_per_panel;
    private $hsp;
    private $electricity_price;
    private $watts_per_panel;

    public function __construct() {
        $this->load_prices();
        $this->load_technical_params();
    }

    /**
     * Carga precios desde wp_options o usa defaults
     */
    private function load_prices() {
        $default_installations = array(
            array('power' => 2, 'panels' => 4, 'price' => 2820),
            array('power' => 3, 'panels' => 6, 'price' => 3815),
            array('power' => 4, 'panels' => 7, 'price' => 4475),
            array('power' => 5, 'panels' => 9, 'price' => 5385),
            array('power' => 6, 'panels' => 11, 'price' => 6395),
            array('power' => 8, 'panels' => 14, 'price' => 7585),
            array('power' => 10, 'panels' => 17, 'price' => 8870),
        );

        $default_batteries = array(
            '5kwh'  => 1356.75,
            '10kwh' => 2451.15,
            '15kwh' => 3536.55,
        );

        $this->installations = get_option('origen_installation_prices', $default_installations);
        $this->batteries     = get_option('origen_battery_prices', $default_batteries);
        $this->ve_charger_price = floatval(get_option('origen_ve_charger_price', 995));
    }

    /**
     * Carga parámetros técnicos desde wp_options
     */
    private function load_technical_params() {
        $this->sqm_per_panel    = floatval(get_option('origen_sqm_per_panel', 2.65));
        $this->hsp              = floatval(get_option('origen_hsp_hours', 4.8));
        $this->electricity_price = floatval(get_option('origen_electricity_price', 0.2));
        $this->watts_per_panel  = intval(get_option('origen_watts_per_panel', 590));
    }

    /**
     * Obtener instalaciones
     */
    public function get_installations() {
        return $this->installations;
    }

    /**
     * Obtener baterías
     */
    public function get_batteries() {
        return $this->batteries;
    }

    /**
     * Obtener precio cargador VE
     */
    public function get_ve_charger_price() {
        return $this->ve_charger_price;
    }

    /**
     * Obtener parámetros técnicos para frontend JS
     */
    public function get_technical_params() {
        return array(
            'sqm_per_panel'    => $this->sqm_per_panel,
            'hsp'              => $this->hsp,
            'electricity_price' => $this->electricity_price,
            'watts_per_panel'  => $this->watts_per_panel,
        );
    }

    /**
     * Calcula kW necesarios por consumo en kWh
     */
    public function kw_by_kwh($consumption_value, $custom_value = null) {
        if ($consumption_value === 'otro_kwh' && $custom_value) {
            $kwh_mes = floatval($custom_value);
        } else {
            $averages = array(
                'menos_200' => 150,
                '200_500'   => 350,
                'mas_500'   => 650,
            );
            $kwh_mes = isset($averages[$consumption_value]) ? $averages[$consumption_value] : 350;
        }

        $kwh_dia = $kwh_mes / 30;
        return $kwh_dia / $this->hsp;
    }

    /**
     * Calcula kW necesarios por gasto en euros
     */
    public function kw_by_euros($consumption_value, $custom_value = null) {
        if ($consumption_value === 'otro_euros' && $custom_value) {
            $euro_mes = floatval($custom_value);
        } else {
            $averages = array(
                '50_150'  => 100,
                '150_300' => 225,
                'mas_300' => 400,
            );
            $euro_mes = isset($averages[$consumption_value]) ? $averages[$consumption_value] : 225;
        }

        $euro_dia = $euro_mes / 30;
        $kwh_dia  = $euro_dia / $this->electricity_price;
        return $kwh_dia / $this->hsp;
    }

    /**
     * Calcula kW máximos por superficie disponible
     */
    public function kw_by_surface($surface_value, $custom_value = null) {
        if ($surface_value === 'otro_superficie' && $custom_value) {
            $sqm = floatval($custom_value);
        } else {
            $surfaces = array(
                'menos_20'  => 15,
                '20_50'     => 35,
                'mas_50'    => 65,
                'no_seguro' => 35,
            );
            $sqm = isset($surfaces[$surface_value]) ? $surfaces[$surface_value] : 35;
        }

        $panels_possible = floor($sqm / $this->sqm_per_panel);

        // Buscar instalación que quepa en ese número de placas
        $kw_surface = 0;
        foreach ($this->installations as $inst) {
            if ($inst['panels'] <= $panels_possible) {
                $kw_surface = $inst['power'];
            }
        }

        // Si ninguna cabe, usar la más pequeña
        if ($kw_surface === 0 && !empty($this->installations)) {
            $kw_surface = $this->installations[0]['power'];
        }

        return $kw_surface;
    }

    /**
     * Selecciona la instalación más cercana (hacia arriba)
     */
    public function find_installation($kw_needed) {
        $selected = end($this->installations); // Por defecto, la más grande

        foreach ($this->installations as $inst) {
            if ($inst['power'] >= $kw_needed) {
                $selected = $inst;
                break;
            }
        }

        return $selected;
    }

    /**
     * Cálculo completo del presupuesto
     */
    public function calculate($data) {
        // Criterio A o B: kW por consumo
        $consumption_type  = isset($data['consumption_type']) ? $data['consumption_type'] : 'kwh';
        $consumption_value = isset($data['consumption_value']) ? $data['consumption_value'] : '';

        // Valores personalizados
        $custom_kwh     = isset($data['consumption_other_kwh']) ? $data['consumption_other_kwh'] : null;
        $custom_euros   = isset($data['consumption_other_euros']) ? $data['consumption_other_euros'] : null;
        $custom_surface = isset($data['roof_surface_other']) ? $data['roof_surface_other'] : null;

        if ($consumption_type === 'euro') {
            $kw_consumption = $this->kw_by_euros($consumption_value, $custom_euros);
        } else {
            $kw_consumption = $this->kw_by_kwh($consumption_value, $custom_kwh);
        }

        // Criterio C: kW por superficie
        $surface_value = isset($data['roof_surface']) ? $data['roof_surface'] : 'no_seguro';
        $kw_surface    = $this->kw_by_surface($surface_value, $custom_surface);

        // Selección final: mínimo entre consumo y superficie
        $kw_final = $kw_consumption;
        if ($kw_final > $kw_surface) {
            $kw_final = $kw_surface;
        }

        // Determinar criterio de ajuste usado
        $adjustment_reason = '';
        if ($kw_consumption > $kw_surface) {
            $adjustment_reason = 'superficie';
        } elseif ($consumption_type === 'euro') {
            $adjustment_reason = 'euros';
        } else {
            $adjustment_reason = 'kwh';
        }

        // Buscar instalación
        $installation = $this->find_installation($kw_final);

        // Precio batería
        $battery_option = isset($data['battery_option']) ? $data['battery_option'] : 'none';
        $battery_price  = 0;
        if ($battery_option !== 'none' && isset($this->batteries[$battery_option])) {
            $battery_price = floatval($this->batteries[$battery_option]);
        }

        // Precio cargador VE (ahora es radio button, no checkbox)
        $ve_charger_val = isset($data['ve_charger']) ? $data['ve_charger'] : 'no';
        $wants_ve = ($ve_charger_val === 'si');
        // Mantener retrocompatibilidad con campo anterior
        if (!$wants_ve && isset($data['wants_ve_charger'])) {
            $wants_ve = ($data['wants_ve_charger'] === 'si');
        }
        $ve_price = $wants_ve ? $this->ve_charger_price : 0;

        // Precio total
        $base_price  = floatval($installation['price']);
        $total_price = $base_price + $battery_price + $ve_price;

        // Ahorro y amortización
        $kwh_generated_month = $installation['power'] * $this->hsp * 30;
        $monthly_savings     = $kwh_generated_month * $this->electricity_price;
        $annual_savings      = $monthly_savings * 12;
        $payback_years       = ($annual_savings > 0) ? $total_price / $annual_savings : 0;

        return array(
            'recommended_power'  => $installation['power'],
            'recommended_panels' => $installation['panels'],
            'base_price'         => $base_price,
            'battery_price'      => $battery_price,
            've_price'           => $ve_price,
            'total_price'        => $total_price,
            'annual_savings'     => round($annual_savings, 2),
            'payback_years'      => round($payback_years, 1),
            'kw_consumption'     => round($kw_consumption, 2),
            'kw_surface'         => round($kw_surface, 2),
            'adjustment_reason'  => $adjustment_reason,
        );
    }
}
