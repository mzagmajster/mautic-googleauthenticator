<?php
namespace MauticPlugin\HostnetAuthBundle\Integration;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Model\NotificationModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\PluginBundle\Model\IntegrationEntityModel;
use Mautic\LeadBundle\Model\DoNotContact as DoNotContactModel;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use MauticPlugin\HostnetAuthBundle\Helper\NotationHelper;
use MauticPlugin\HostnetAuthBundle\Helper\AuthenticatorHelper;


class HostnetAuthIntegration extends AbstractIntegration
{
    private $twig;

    protected $user;

    protected $status_field;
    
    protected $secret_field;

    protected $gauth;
    
    protected $secret;




    public function log_message($message) {
        $logFile = '/var/www/html/var/logs/my_log.txt';
        $formattedMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

        public function __construct(
        EventDispatcherInterface $eventDispatcher,
        CacheStorageHelper $cacheStorageHelper,
        EntityManager $entityManager,
        SessionInterface $session,
        RequestStack $requestStack,
        RouterInterface $router,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        EncryptionHelper $encryptionHelper,
        LeadModel $leadModel,
        CompanyModel $companyModel,
        PathsHelper $pathsHelper,
        NotificationModel $notificationModel,
        FieldModel $fieldModel,
        IntegrationEntityModel $integrationEntityModel,
        DoNotContactModel $doNotContact,
        UserHelper $userHelper
    ) {
        $this->log_message('__construct');
        parent::__construct(
            $eventDispatcher,
            $cacheStorageHelper,
            $entityManager,
            $session,
            $requestStack,
            $router,
            $translator,
            $logger,
            $encryptionHelper,
            $leadModel,
            $companyModel,
            $pathsHelper,
            $notificationModel,
            $fieldModel,
            $integrationEntityModel,
            $doNotContact
        );

        $this->userHelper   = $userHelper;
        $this->user = $this->userHelper->getUser();

        $id = $this->user->getId();
        $this->status_field = "scanned_$id";
        $this->secret_field = "secret_$id";
        $this->cookie_field = "cookie_$id";

        $this->gauth = new AuthenticatorHelper();
    }




    public function getName(): string
    {
        return 'HostnetAuth';
    }

    public function getDisplayName(): string
    {
        return 'Google Authenticator';
    }

    /**
     * @return array<string, string>
     */
    public function getRequiredKeyFields(): array
    {
        return [
            $this->cookie_field     => 'mautic.integration.auth.cookie_duration',
        ];
    }

    public function getAuthenticationType()
    {
        return 'none';
    }

    /**
     * @param Form|FormBuilder $builder
     * @param array            $data
     * @param string           $formArea
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('keys' === $formArea) {
            $builder
                ->add(
                    $this->status_field,
                    YesNoButtonGroupType::class,
                    [
                        'label' => 'mautic.integration.auth.scanned',
                        'data'  => $this->isConfigured(),
                        'attr'  => [
                            'tooltip' => 'You must scan the code with your phone to use the plugin.',
                        ],
                    ]
                )
                ->add(
                    $this->cookie_field,
                    NumberType::class,
                    [
                        'label' => 'mautic.integration.auth.cookie_duration',
                        'data'  => $this->getCookieDuration(),
                        'attr'  => [
                            'tooltip' => 'You won\'t be prompted for codes in trusted browsers',
                            'class' => 'form-control'
                        ],
                    ]
                )
                ->add(
                    $this->secret_field,
                    HiddenType::class,
                    [
                        'data'  => $this->getGauthSecret()
                    ]
                );
       }
    }

        /**
     * {@inheritdoc}
     *
     * @param $section
     *
     * @return string|array
     */
    public function getFormNotes($section)
    {

        $this->log_message("getFormNotes " . $section);

        if ('custom' === $section) {

            $url = $this->router->generate(
                'mautic_dashboard_index',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $url = preg_replace('/http[s]?:\/\/|\/s\/dashboard/i', '', $url);

            return [
                'template'   => 'HostnetAuthBundle:Integration:form.html.php',
                'parameters' => [
                    'secret' => $this->secret,
                    'qrUrl'  => $this->gauth->getURL(
                        $this->user->getUsername(),
                        $url,
                        $this->secret
                    ),
                ],
            ];
        }

        return parent::getFormNotes($section);
    }

    public function getGauthSecret(): string
    {
        $featureSettings = $this->getKeys();

        $this->secret = isset($featureSettings[$this->secret_field])
            ? $featureSettings[$this->secret_field]
            : $this->gauth->generateSecret();

        return $this->secret;
    }

    public function isConfigured(): bool
    {
        $featureSettings = $this->getKeys();

        return isset($featureSettings[$this->status_field])
            ? (bool) $featureSettings[$this->status_field]
            : false;
    }

    public function getCookieDuration(): int
    {
        $featureSettings = $this->getKeys();

        return isset($featureSettings[$this->cookie_field])
            ? $featureSettings[$this->cookie_field]
            : 30;
    }

    public function getSupportedFeatures()
    {
        return [];
    }
}