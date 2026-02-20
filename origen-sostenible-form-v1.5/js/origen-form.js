/**
 * Origen Sostenible - Formulario Evaluación Energética v1.5
 * Frontend JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';

    var currentStep = 1;
    var skipToEnd = false;

    // Constantes de cálculo
    var HSP = 4.8;
    var ELECTRICITY_PRICE = 0.2;
    var SQM_PER_PANEL = 2.65;

    // Datos de precios desde WordPress
    var installations = origenForm.installations || [];
    var batteries = origenForm.batteries || {};
    var veChargerPrice = parseFloat(origenForm.ve_charger) || 995;

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    showStep(1);
    updateDynamicPrices();

    // =========================================================================
    // NAVEGACIÓN
    // =========================================================================

    $('#btnNext').on('click', function() {
        if (!validateStep(currentStep)) {
            return;
        }

        // Lógica especial paso 7
        if (currentStep === 7) {
            var wantsQuote = $('input[name="wants_quote"]:checked').val();
            if (wantsQuote === 'no') {
                skipToEnd = true;
                submitForm();
                return;
            }
        }

        // Al llegar al paso 15, calcular presupuesto
        if (currentStep === 14) {
            calculateBudget();
        }

        currentStep++;
        showStep(currentStep);
    });

    $('#btnPrev').on('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    $('#btnSubmit').on('click', function(e) {
        e.preventDefault();
        submitForm();
    });

    // =========================================================================
    // MOSTRAR PASO
    // =========================================================================

    function showStep(step) {
        $('.origen-form-step').removeClass('active');
        $('.origen-form-step[data-step="' + step + '"]').addClass('active');
        updateProgress();
        updateButtons();

        // Scroll al inicio del formulario
        $('html, body').animate({
            scrollTop: $('#origenFormWrapper').offset().top - 20
        }, 300);
    }

    function updateProgress() {
        var displayStep = currentStep;
        var displayTotal = 6;

        if (currentStep === 7) {
            displayStep = 6;
            displayTotal = 6;
        } else if (currentStep > 7) {
            displayTotal = 15;
        }

        var percent = (displayStep / displayTotal) * 100;
        $('#progressBar').css('width', Math.min(percent, 100) + '%');
        $('#stepIndicator').text(displayStep + ' de ' + displayTotal);
    }

    function updateButtons() {
        // Anterior
        if (currentStep <= 1) {
            $('#btnPrev').hide();
        } else {
            $('#btnPrev').show();
        }

        // Siguiente vs Enviar
        if (currentStep === 15) {
            $('#btnNext').hide();
            $('#btnSubmit').show();
        } else {
            $('#btnNext').show();
            $('#btnSubmit').hide();
        }
    }

    // =========================================================================
    // TOGGLE kWh / €
    // =========================================================================

    $('.consumption-toggle').on('click', function() {
        var mode = $(this).data('mode');

        // Actualizar botones toggle
        $('.consumption-toggle').removeClass('active');
        $(this).addClass('active');

        // Actualizar hidden input
        $('#consumptionType').val(mode);

        // Desmarcar todas las opciones de consumo
        $('.consumption-options input[type="radio"]').prop('checked', false);

        // Mostrar/ocultar opciones
        $('.consumption-options').hide();
        $('.consumption-options[data-type="' + mode + '"]').show();
    });

    // =========================================================================
    // SELECCIÓN VISUAL (click en tarjetas)
    // =========================================================================

    $(document).on('click', '.origen-option-card', function() {
        var input = $(this).find('input');

        if (input.attr('type') === 'radio') {
            // Para radio: deseleccionar hermanos visibles
            var name = input.attr('name');
            $(this).closest('.origen-options-grid').find('input[name="' + name + '"]')
                .closest('.origen-option-card').removeClass('selected');
            $(this).addClass('selected');
            input.prop('checked', true).trigger('change');
        } else if (input.attr('type') === 'checkbox') {
            // Para checkbox: toggle
            var isChecked = input.prop('checked');
            input.prop('checked', !isChecked).trigger('change');
            $(this).toggleClass('selected', !isChecked);
        }
    });

    // =========================================================================
    // VALIDACIÓN
    // =========================================================================

    function validateStep(step) {
        var $step = $('.origen-form-step[data-step="' + step + '"]');
        var isValid = true;

        // Limpiar errores previos
        $step.find('.origen-field-error').removeClass('origen-field-error');
        $step.find('.origen-error-text').remove();

        switch(step) {
            case 1: // Tipo de propiedad
                if (!$('input[name="property_type"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un tipo de propiedad.');
                    isValid = false;
                }
                break;

            case 2: // Consumo
                var activeType = $('#consumptionType').val();
                var checked = $('.consumption-options[data-type="' + activeType + '"] input[type="radio"]:checked').length;
                if (!checked) {
                    showStepError($step, 'Por favor, selecciona tu consumo aproximado.');
                    isValid = false;
                }
                break;

            case 3: // Servicios (al menos uno)
                if (!$('input[name="services[]"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona al menos un servicio.');
                    isValid = false;
                }
                break;

            case 4: // Plazo
                if (!$('input[name="timeframe"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un plazo.');
                    isValid = false;
                }
                break;

            case 5: // Ubicación
                if (!$('#location').val().trim()) {
                    $('#location').addClass('origen-field-error');
                    showFieldError($('#location'), 'Este campo es obligatorio.');
                    isValid = false;
                }
                break;

            case 6: // Datos de contacto
                var name = $('#name').val().trim();
                var email = $('#email').val().trim();
                var phone = $('#phone').val().trim();
                var privacy = $('input[name="privacy_accepted"]').is(':checked');

                if (!name) {
                    $('#name').addClass('origen-field-error');
                    showFieldError($('#name'), 'El nombre es obligatorio.');
                    isValid = false;
                }
                if (!email || !isValidEmail(email)) {
                    $('#email').addClass('origen-field-error');
                    showFieldError($('#email'), 'Introduce un email válido.');
                    isValid = false;
                }
                if (!phone) {
                    $('#phone').addClass('origen-field-error');
                    showFieldError($('#phone'), 'El teléfono es obligatorio.');
                    isValid = false;
                }
                if (!privacy) {
                    showStepError($step, 'Debes aceptar la política de privacidad.');
                    isValid = false;
                }
                break;

            case 7: // Presupuesto
                if (!$('input[name="wants_quote"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opción.');
                    isValid = false;
                }
                break;

            case 8:
                if (!$('input[name="roof_type"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un tipo de techo.');
                    isValid = false;
                }
                break;

            case 9:
                if (!$('input[name="roof_orientation"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una orientación.');
                    isValid = false;
                }
                break;

            case 10:
                if (!$('input[name="roof_surface"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una superficie.');
                    isValid = false;
                }
                break;

            case 11:
                if (!$('input[name="financial_capacity"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opción.');
                    isValid = false;
                }
                break;

            case 12:
                if (!$('input[name="decision_making"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opción.');
                    isValid = false;
                }
                break;

            case 13:
                if (!$('input[name="motivation"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una motivación.');
                    isValid = false;
                }
                break;

            case 14:
                if (!$('input[name="battery_option"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opción de baterías.');
                    isValid = false;
                }
                break;
        }

        return isValid;
    }

    function showStepError($step, message) {
        if (!$step.find('.origen-error-text').length) {
            $step.append('<p class="origen-error-text" style="text-align: center; margin-top: 10px;">' + message + '</p>');
        }
    }

    function showFieldError($field, message) {
        if (!$field.next('.origen-error-text').length) {
            $field.after('<p class="origen-error-text">' + message + '</p>');
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Limpiar errores al interactuar
    $(document).on('change', 'input, textarea, select', function() {
        $(this).removeClass('origen-field-error');
        $(this).next('.origen-error-text').remove();
        $(this).closest('.origen-form-step').find('> .origen-error-text').remove();
    });

    // =========================================================================
    // CÁLCULO DE PRESUPUESTO
    // =========================================================================

    function calculateBudget() {
        // Obtener valores
        var consumptionType = $('#consumptionType').val();
        var consumptionValue = $('input[name="consumption"]:checked').val();
        var roofSurface = $('input[name="roof_surface"]:checked').val();
        var batteryOption = $('input[name="battery_option"]:checked').val();
        var wantsVE = $('input[name="wants_ve_charger"]').is(':checked');

        // Criterio A o B: kW por consumo
        var kwConsumption = 0;
        if (consumptionType === 'euro') {
            kwConsumption = kwByEuros(consumptionValue);
        } else {
            kwConsumption = kwByKwh(consumptionValue);
        }

        // Criterio C: kW por superficie
        var kwSurface = kwBySurface(roofSurface);

        // Selección final
        var kwFinal = kwConsumption;
        if (kwFinal > kwSurface) {
            kwFinal = kwSurface;
        }

        // Buscar instalación adecuada
        var installation = findInstallation(kwFinal);

        // Precios
        var basePrice = installation.price;
        var batteryPrice = 0;
        if (batteryOption !== 'none' && batteries[batteryOption]) {
            batteryPrice = parseFloat(batteries[batteryOption]);
        }
        var vePrice = wantsVE ? veChargerPrice : 0;
        var totalPrice = basePrice + batteryPrice + vePrice;

        // Ahorro y amortización
        var kwhGeneratedMonth = installation.power * HSP * 30;
        var monthlySavings = kwhGeneratedMonth * ELECTRICITY_PRICE;
        var annualSavings = monthlySavings * 12;
        var paybackYears = annualSavings > 0 ? totalPrice / annualSavings : 0;

        // Mostrar resultados
        $('#recommendedPower').text(installation.power + ' kW');
        $('#recommendedPanels').text(installation.panels + ' placas');
        $('#basePrice').text(formatCurrency(basePrice));

        // Extras
        var extrasText = [];
        if (batteryPrice > 0) {
            extrasText.push('Batería: +' + formatCurrency(batteryPrice));
        }
        if (vePrice > 0) {
            extrasText.push('Cargador V.E.: +' + formatCurrency(vePrice));
        }
        if (extrasText.length > 0) {
            $('#extrasDetail').html(extrasText.join('<br>'));
            $('#extrasRow').show();
        } else {
            $('#extrasRow').hide();
        }

        $('#totalPrice').text(formatCurrency(totalPrice));
        $('#annualSavings').text(formatCurrency(annualSavings) + '/año');
        $('#paybackYears').text(paybackYears.toFixed(1) + ' años');

        // Guardar en campos ocultos
        $('#calcRecommendedPower').val(installation.power);
        $('#calcRecommendedPanels').val(installation.panels);
        $('#calcBasePrice').val(basePrice.toFixed(2));
        $('#calcBatteryPrice').val(batteryPrice.toFixed(2));
        $('#calcVePrice').val(vePrice.toFixed(2));
        $('#calcTotalPrice').val(totalPrice.toFixed(2));
        $('#calcAnnualSavings').val(annualSavings.toFixed(2));
        $('#calcPaybackYears').val(paybackYears.toFixed(1));
    }

    function kwByKwh(value) {
        var averages = {
            'menos_200': 150,
            '200_500': 350,
            'mas_500': 650
        };
        var kwhMes = averages[value] || 350;
        var kwhDia = kwhMes / 30;
        return kwhDia / HSP;
    }

    function kwByEuros(value) {
        var averages = {
            '50_150': 100,
            '150_300': 225,
            'mas_300': 400
        };
        var euroMes = averages[value] || 225;
        var euroDia = euroMes / 30;
        var kwhDia = euroDia / ELECTRICITY_PRICE;
        return kwhDia / HSP;
    }

    function kwBySurface(value) {
        var surfaces = {
            'menos_20': 15,
            '20_50': 35,
            'mas_50': 65,
            'no_seguro': 35
        };
        var sqm = surfaces[value] || 35;
        var panelsPossible = Math.floor(sqm / SQM_PER_PANEL);

        var kwSurface = 0;
        for (var i = 0; i < installations.length; i++) {
            if (installations[i].panels <= panelsPossible) {
                kwSurface = installations[i].power;
            }
        }

        if (kwSurface === 0 && installations.length > 0) {
            kwSurface = installations[0].power;
        }

        return kwSurface;
    }

    function findInstallation(kwNeeded) {
        var selected = installations[installations.length - 1]; // Default: la más grande

        for (var i = 0; i < installations.length; i++) {
            if (installations[i].power >= kwNeeded) {
                selected = installations[i];
                break;
            }
        }

        return selected;
    }

    function formatCurrency(value) {
        return value.toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '€';
    }

    // =========================================================================
    // PRECIOS DINÁMICOS EN EL FORMULARIO
    // =========================================================================

    function updateDynamicPrices() {
        if (batteries['5kwh']) {
            $('.battery-price-5kwh').text(parseFloat(batteries['5kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        if (batteries['10kwh']) {
            $('.battery-price-10kwh').text(parseFloat(batteries['10kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        if (batteries['15kwh']) {
            $('.battery-price-15kwh').text(parseFloat(batteries['15kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        $('.ve-charger-price').text(veChargerPrice.toLocaleString('es-ES', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
    }

    // =========================================================================
    // ENVÍO FORMULARIO
    // =========================================================================

    function submitForm() {
        var $form = $('#origenEvaluationForm');

        // Mostrar spinner
        $('#navButtons').hide();
        $('#origenSpinner').show();

        var formData = new FormData($form[0]);
        formData.append('action', 'origen_submit_form');
        formData.append('nonce', origenForm.nonce);

        $.ajax({
            url: origenForm.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#origenSpinner').hide();
                if (response.success) {
                    // Mostrar éxito
                    $('.origen-form-step').removeClass('active');
                    $('.origen-progress-container').hide();
                    $('#successMessage').show();
                    $('#navButtons').hide();

                    $('html, body').animate({
                        scrollTop: $('#origenFormWrapper').offset().top - 20
                    }, 300);
                } else {
                    alert(response.data.message || 'Error al enviar el formulario.');
                    $('#navButtons').show();
                }
            },
            error: function() {
                $('#origenSpinner').hide();
                alert('Error de conexión. Por favor, inténtalo de nuevo.');
                $('#navButtons').show();
            }
        });
    }

});
