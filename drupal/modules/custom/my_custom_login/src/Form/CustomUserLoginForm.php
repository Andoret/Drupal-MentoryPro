<?php
namespace Drupal\my_custom_login\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\user\UserFloodControlInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class CustomUserLoginForm extends FormBase {

  protected $userFloodControl;
  protected $userStorage;
  protected $renderer;
  protected $bareHtmlPageRenderer;
  protected $httpClient;

  public function __construct(UserFloodControlInterface $user_flood_control, UserStorageInterface $user_storage, RendererInterface $renderer, BareHtmlPageRendererInterface $bare_html_renderer, ClientInterface $http_client) {
    $this->userFloodControl = $user_flood_control;
    $this->userStorage = $user_storage;
    $this->renderer = $renderer;
    $this->bareHtmlPageRenderer = $bare_html_renderer;
    $this->httpClient = $http_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.flood_control'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('renderer'),
      $container->get('bare_html_page_renderer'),
      $container->get('http_client')
    );
  }

  public function getFormId() {
    return 'custom_user_login_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('system.site');

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#size' => 60,
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'none',
        'autocapitalize' => 'none',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
        'autocomplete' => 'username',
      ],
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#size' => 60,
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'current-password',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Log in')];

    $this->renderer->addCacheableDependency($form, $config);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $account = $this->userStorage->load($form_state->get('uid'));

    $form_state->setRedirect('some_custom_route');
    user_login_finalize($account);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = trim($form_state->getValue('name'));
    $password = trim($form_state->getValue('pass'));

    try {
 
      $response = $this->httpClient->post('http://tpbooks5.teleperformance.co/api/authenticate/login/', [
        'json' => [
          'name_user' => $username,
          'password' => $password,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);

      if ($data['response']['status']=== true) {
         
          
          $form_state->setRedirect('http://tpbooks5.teleperformance.co/admin');
         
      } else {
        $form_state->setErrorByName('name', $this->t('Invalid username or password.'));

      }
    } catch (RequestException $e) {
      $form_state->setErrorByName('name', $this->t('An error occurred while contacting the authentication service.'));
    }
  }
}