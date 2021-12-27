<?php
/**
 * @package    n3t HaveIBeenPwnd
 *
 * @author     Pavel Poles - n3t.cz
 * @copyright  © 2021 Pavel Poles - n3t.cz. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://n3t.bitbucket.io
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Version;

class plgUserN3tHibp extends CMSPlugin
{
  private const API_URL = 'https://api.pwnedpasswords.com/range/';
  private const USER_AGENT = 'n3t HaveIBeenPwnd for Joomla! CMS [https://n3t.bitbucket.io]';

  protected $autoloadLanguage = true;

  /** @var CMSApplication */
  private $app = null;

  public function __construct(&$subject, $config = array())
  {
    parent::__construct($subject, $config);
    $this->app = Factory::getApplication();
  }

  private function isPasswordPwnd(string $password): bool
  {
    $passwordHash = strtoupper(sha1($password));
    $passwordHash5 = substr($passwordHash, 0, 5);
    $passwordHash = substr($passwordHash, 5);

    $http = HttpFactory::getHttp();
    $http->setOption('User-Agent', self::USER_AGENT);
    $response = $http->get(self::API_URL . $passwordHash5);

    if ($response->code !== 200)
      return false;

    if (!$response->body)
      return false;

    $matches = explode("\n", $response->body);
    foreach ($matches as $match) {
      list($match, $count) = explode(':', $match);
      if ($match == $passwordHash) {
        return ($count > $this->params->get('max_count', 0));
      }
    }

    return false;
  }

  public function onUserBeforeSave(array $oldUser, bool $isNew, array $newUser): bool
  {
    if (!isset($newUser['password_clear']) || empty($newUser['password_clear']))
      return true;

    if (!$this->params->get('check_save', 1))
      return true;

	/** this needs to be debugged, error maessages dont work as expected*/
    if ($this->isPasswordPwnd($newUser['password_clear'])) {
      if ($this->params->get('disable_save', 0)) {
        if (Version::MAJOR_VERSION == 3) {
          $user = Factory::getUser();
          $this->app->enqueueMessage(Text::_('PLG_USER_N3THIBP_PASSOWRD_PWND'), 'warning');
          $user->setError(Text::_('PLG_USER_N3THIBP_PASSOWRD_PWND'));
          return false;
        } else
          throw new \InvalidArgumentException(Text::_('PLG_USER_N3THIBP_PASSOWRD_PWND'));
      } else
        $this->app->enqueueMessage(Text::_('PLG_USER_N3THIBP_PASSOWRD_PWND'), 'warning');
    }

    return true;
  }

  public function onUserLogin(array $user, array $options): bool
  {
    if (!$this->params->get('check_login', 1))
      return true;

    if (!isset($user['password']) || empty($user['password']))
      return true;

    if ($this->isPasswordPwnd($user['password']))
      $this->app->enqueueMessage(Text::_('PLG_USER_N3THIBP_PASSOWRD_PWND_ON_LOGIN'), 'warning');

    return true;
  }
}
