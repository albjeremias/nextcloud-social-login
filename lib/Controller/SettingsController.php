<?php

namespace OCA\SocialLogin\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;
use OCA\SocialLogin\Db\SocialConnectDAO;

class SettingsController extends Controller
{
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IUserSession */
    private $userSession;
    /** @var IL10N */
    private $l;
    /** @var SocialConnectDAO */
    private $socialConnect;

    public function __construct(
        $appName,
        IRequest $request,
        IConfig $config,
        IURLGenerator $urlGenerator,
        IUserSession $userSession,
        IL10N $l,
        SocialConnectDAO $socialConnect
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->l = $l;
        $this->socialConnect = $socialConnect;
    }

    public function saveAdmin($new_user_group, $disable_registration, $allow_login_connect, $providers, $openid_providers, $custom_oidc_providers)
    {
        try {
            $this->checkProviders($openid_providers);
            $this->checkProviders($custom_oidc_providers);
        } catch (\Exception $e) {
            return new JSONResponse(['message' => $e->getMessage()]);
        }
        $this->config->setAppValue($this->appName, 'new_user_group', $new_user_group);
        $this->config->setAppValue($this->appName, 'disable_registration', $disable_registration ? true : false);
        $this->config->setAppValue($this->appName, 'allow_login_connect', $allow_login_connect ? true : false);
        $this->config->setAppValue($this->appName, 'oauth_providers', json_encode($providers));
        $this->config->setAppValue($this->appName, 'openid_providers', json_encode(array_values($openid_providers)));
        $this->config->setAppValue($this->appName, 'custom_oidc_providers', json_encode(array_values($custom_oidc_providers)));
        return new JSONResponse(['success' => true]);
    }

    private function checkProviders(array $providers)
    {
        $titles = [];
        foreach ($providers as $provider) {
            $title = $provider['title'];
            if (in_array($title, $titles)) {
                throw new \Exception($this->l->t('Duplicate provider title "%s"', $title));
            }
            if (preg_match('#[^0-9a-z_.@-]#i', $title)) {
                throw new \Exception($this->l->t('Invalid provider title "%s". Allowed characters "0-9a-z_.@-"', $title));
            }
            $titles[] = $title;
        }
    }

    public function renderPersonal()
    {
        $params = [
            'providers' => [],
            'connected_logins' => [],
        ];
        $providers = json_decode($this->config->getAppValue($this->appName, 'oauth_providers', '[]'), true);
        if (is_array($providers)) {
            foreach ($providers as $title=>$provider) {
                if ($provider['appid']) {
                    $params['providers'][ucfirst($title)] = $this->urlGenerator->linkToRoute($this->appName.'.login.oauth', ['provider'=>$title]);
                }
            }
        }
        $providers = json_decode($this->config->getAppValue($this->appName, 'openid_providers', '[]'), true);
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                $title = $provider['title'];
                $params['providers'][ucfirst($title)] = $this->urlGenerator->linkToRoute($this->appName.'.login.openid', ['provider'=>$title]);
            }
        }
        $providers = json_decode($this->config->getAppValue($this->appName, 'custom_oidc_providers', '[]'), true);
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                $title = $provider['title'];
                $params['providers'][ucfirst($title)] = $this->urlGenerator->linkToRoute($this->appName.'.login.custom_oidc', ['provider'=>$title]);
            }
        }

        $uid = $this->userSession->getUser()->getUID();
        $connectedLogins = $this->socialConnect->getConnectedLogins($uid);
        foreach ($connectedLogins as $login) {
            $params['connected_logins'][$login] = $this->urlGenerator->linkToRoute($this->appName.'.settings.disconnectSocialLogin', [
                'login' => $login,
                'requesttoken' => Util::callRegister(),
            ]);
        }
        return (new TemplateResponse($this->appName, 'personal', $params, ''))->render();
    }

    /**
     * @NoAdminRequired
     */
    public function disconnectSocialLogin($login)
    {
        $this->socialConnect->disconnectLogin($login);
        return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section'=>'additional']));
    }
}
