<?php

namespace MauticPlugin\HostnetAuthBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class UserSubscriber.
 *
 * @author Henrique Rodrigues <henrique@hostnet.com.br>
 *
 * @see https://www.hostnet.com.br
 */
class UserSubscriber implements EventSubscriberInterface
{
    protected $router;
    protected $security;
    protected $session;
    protected $integration;

    /**
     * UserSubscriber constructor.
     */
    public function __construct(
        RouterInterface $router,
        CorePermissions $security,
        IntegrationHelper $integration,
        UserHelper $user,
        EntityManager $em
    ) {
        $this->router      = $router;
        $this->security    = $security;
        $this->integration = $integration;
        $this->user        = $user;
        $this->em          = $em;
    }

    /**orm
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    /**
     * verifies if the user is authenticated and gives the right response.
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return false;
        }

        $myIntegration = $this->integration->getIntegrationObject('HostnetAuth');

        if (!$myIntegration) {
            return false;
        }

        $published = $myIntegration->getIntegrationSettings()->getIsPublished();

        if (!$published || !$myIntegration->isConfigured()) {
            return false;
        }

        $request    = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $userId     = $this->user->getUser()->getId();

        $gauthGranted = $this->isSafeBrowser(
            $request->cookies,
            $userId,
            $myIntegration->getCookieDuration(),
            $myIntegration
        )
            ? true
            : $request->getSession()->get('gauth_granted');

        $needVerification = (!$this->security->isAnonymous()) // User logged in
            && !preg_match('/gauth|login|HostnetAuth|api/i', $requestUri) // it's not an authentication url
            && !$gauthGranted // user not authenticated
;

        if ($needVerification) {
            $request->getSession()->set('gauth_granted', false);
            $generateUrl = $this->router->generate('hostnet_google_authenticator');
            $event->setResponse(new RedirectResponse($generateUrl));
        }
    }

    public function isSafeBrowser($cookies, $userId, $cookieDuration, $myIntegration)
    {
        if (!$cookies->has('plugin_browser_hash')) {
            return false;
        }

        $hash = $cookies->get('plugin_browser_hash');

        $browsers = $this->em->getRepository(
            \MauticPlugin\HostnetAuthBundle\Entity\AuthBrowser::class
        )->findBy([
            'user_id' => $userId,
            'hash'    => $hash,
        ]);

        if (empty($browsers)) {
            return false;
        }

        $currentBrowser = current($browsers);

        $currentDate = new \DateTime();
        $currentDate->setTimezone(new \DateTimeZone('UTC'));

        $cookieAge = $currentDate->diff($currentBrowser->getDateAdded())->format('%d');

        if ($cookieAge > $cookieDuration) {
            return false;
        }

        return true;
    }
}
