/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(document).ready(function () {

    function updateLicenseKey(action, key)
    {
        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'API',
            method: 'Marketplace.' + action,
            licenseKey: key,
            format: 'JSON'
        }, 'get');
        ajaxRequest.setCallback(function (response) {
            if (response && response.value) {
                var UI = require('piwik/UI');
                var notification = new UI.Notification();
                notification.show('License key updated', {context: 'success'});

                piwikHelper.redirect();
            }
        });
        ajaxRequest.send(false);
    }

    function setLicenseKeyEnabled(enabled)
    {
        $('.marketplace #submit_license_key').prop('disabled', !enabled);
    }

    $('.marketplace #license_key').on('keyup', function () {
        var value = $(this).val();
        setLicenseKeyEnabled(!!value);
    });

    $('.marketplace #remove_license_key').on('click', function () {
        updateLicenseKey('deleteLicenseKey', '');
    });

    $('.marketplace #submit_license_key').on('click', function () {

        var value = $('.marketplace #license_key').val();

        if (!value) {
            return;
        }

        setLicenseKeyEnabled(false);
        updateLicenseKey('saveLicenseKey', value);
    });

    // Keeps the plugin descriptions the same height
    $('.marketplace .plugin .description').dotdotdot({
        after: 'a.more',
        watch: 'window'
    });

    $('a.plugin-details[data-pluginName]').on('click', function (event) {
        event.preventDefault();

        var pluginName = $(this).attr('data-pluginName');
        if (!pluginName) {
            return;
        }

        var activeTab = $(this).attr('data-activePluginTab');
        if (activeTab) {
            pluginName += '!' + activeTab;
        }

        broadcast.propagateNewPopoverParameter('browsePluginDetail', pluginName);
    });

    broadcast.addPopoverHandler('browsePluginDetail', function (value) {
        var pluginName = value;
        var activeTab  = null;

        if (-1 !== value.indexOf('!')) {
            activeTab  = value.substr(value.indexOf('!') + 1);
            pluginName = value.substr(0, value.indexOf('!'));
        }

        var url = 'module=Marketplace&action=pluginDetails&pluginName=' + encodeURIComponent(pluginName);

        if (activeTab) {
            url += '&activeTab=' + encodeURIComponent(activeTab);
        }

        Piwik_Popover.createPopupAndLoadUrl(url, 'details');
    });

});
