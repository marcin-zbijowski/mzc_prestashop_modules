<?php
/**
 * MZC Carrier Attribute — Admin controller (menu redirect)
 *
 * This controller only exists so that the back-office menu link
 * under Shipping redirects to the module's standard configuration page.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMzcCarrierAttributeController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => 'mzccarrierattribute',
            ])
        );
    }
}
