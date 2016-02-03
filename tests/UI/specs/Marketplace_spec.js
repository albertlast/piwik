/*!
 * Piwik - free/libre analytics platform
 *
 * Screenshot tests for main, top and admin menus.
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("Marketplace", function () {
    this.timeout(0);

    var urlBase = 'module=Marketplace&action=overview&';

    function capture(done, screenshotName, test, selector)
    {
        if (!selector) {
            selector = '';
        }
        expect.screenshot(screenshotName).to.be.captureSelector('.marketplace ' + selector, test, done);
    }

    function setEnvironment(mode, consumer)
    {
        if (mode === 'user') {
            testEnvironment.idSitesAdminAccess = [1];
        } else {
            testEnvironment.idSitesAdminAccess = [];
        }

        if (mode === 'multiUserEnvironment') {
            testEnvironment.overrideConfig('General', 'multi_server_environment', '1')
        } else {
            testEnvironment.overrideConfig('General', 'multi_server_environment', '0')
        }

        testEnvironment.consumer = consumer;
        testEnvironment.save();
    }

    ['superuser', 'user', 'multiUserEnvironment'].forEach(function (mode) {

        it('for a user with valid license key should open paid plugins by default', function (done) {
            setEnvironment(mode, 'validLicense');

            capture(done, mode + '_valid_license_paid_plugins', function (page) {
                page.load("?" + urlBase);
            });
        });

        it('for a user with valid license key should be able to open free plugins and see only whitelisted plugins', function (done) {
            setEnvironment(mode, 'validLicense');

            capture(done, mode + '_valid_license_free_plugins', function (page) {
                page.load("?" + urlBase + 'type=free');
            });
        });

        it('for a user without license key should open free plugins by default', function (done) {
            setEnvironment(mode, '');

            capture(done, mode + '_no_license_free_plugins', function (page) {
                page.load("?" + urlBase);
            });
        });

        it('for a user without license key should be able to open paid plugins', function (done) {
            setEnvironment(mode, '');

            capture(done, mode + '_no_license_paid_plugins', function (page) {
                page.load("?" + urlBase + 'type=paid');
            });
        });

        it('for a user with expired license key should open paid plugins by default and show a warning that license is expired', function (done) {
            setEnvironment(mode, 'expiredLicense');

            capture(done, mode + '_expired_license_paid_plugins', function (page) {
                page.load("?" + urlBase);
            });
        });

        it('for a user with expired license key should be able to view free plugins, no restrictions in plugins anymore', function (done) {
            setEnvironment(mode, 'expiredLicense');

            capture(done, mode + '_expired_license_free_plugins', function (page) {
                page.load("?" + urlBase + 'type=free');
            });
        });
    });

    // TODO
    // mock the plugins results that are shown

    // add test for enter invalid license
    // add test for enter valid license (if possible)
    // add test for remove license (if possible)
    // add test for updates (if possible)

});