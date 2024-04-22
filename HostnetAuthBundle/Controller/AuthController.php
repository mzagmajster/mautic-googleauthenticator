<?php


namespace MauticPlugin\HostnetAuthBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\HostnetAuthBundle\Entity\AuthBrowser;
use MauticPlugin\HostnetAuthBundle\Helper\AuthenticatorHelper;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Event\CustomTemplateEvent;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;


class AuthController extends CommonController
{
    protected IntegrationHelper $integrationHelper;

    public function __construct(
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        ?RequestStack $requestStack,
        ?CorePermissions $security,
        IntegrationHelper $integrationHelper

    ) {
        parent::__construct(
            $doctrine,
            $factory,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security
        );
        $this->integrationHelper = $integrationHelper;
    }

    public function authAction(Request $request)
    {
        if ($this->isCsrfTokenValid('gauth', $request->request->get('_csrf_token'))) {
            // $integrationHelper = $this->get('mautic.helper.integration');
            $myIntegration     = $this->integrationHelper->getIntegrationObject('HostnetAuth');

            $secret = $myIntegration->getGauthSecret();

            $code = $request->request->get('_code');

            $ga = new AuthenticatorHelper();

            if ($ga->checkCode($secret, $code)) {
                $trustBrowser = (bool) $request->request->get('trust_browser');

                if ($trustBrowser) {
                    $entityManager = $this->getDoctrine()->getManager();

                    $browser = new AuthBrowser();
                    $browser->setUserId($this->get('mautic.helper.user')->getUser()->getId());
                    $browser->setHash($request->request->get('hash'));
                    $browser->setDateAdded(date('Y-m-d H:i:s'));

                    $entityManager->persist($browser);

                    $entityManager->flush();
                }

                $this->get('session')->set('gauth_granted', true);

                $response =  new RedirectResponse('dashboard');
                $response->headers->setCookie(
                    new Cookie(
                        'plugin_browser_hash',
                        $request->request->get('hash'),
                        (new \DateTime())->add(new \DateInterval("P{$myIntegration->getCookieDuration()}D"))
                    )
                );

                return $response;
            } else {
                $this->addFlashMessage(
                    $this->translator->trans('mautic.plugin.auth.invalid'), 
                    [],
                     'error', 
                     null, 
                     false
                 );
            }
        }

        return $this->delegateView([
            'contentTemplate' => '@HostnetAuth/AuthView/form.html.twig',
            'viewParameters'  => [
            ],
        ]);
    }
}
