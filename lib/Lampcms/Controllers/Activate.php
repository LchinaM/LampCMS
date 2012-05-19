<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */

namespace Lampcms\Controllers;

use \Lampcms\WebPage;
use \Lampcms\User;


class Activate extends WebPage
{

	protected $aRequired = array('eid', 'hash');

	/**
	 * Object of type User
	 * representing the user whose account is being activated
	 *
	 * @var object of type User
	 */
	protected $oActivatedUser;

	/**
	 *
	 * @var array
	 */
	protected $aEmail;

	protected $layoutID = 1;

	/**
	 * Maximum number of days after which
	 * validation code is no longer valid
	 *
	 * Defaults to 604800 which is 7 days
	 *
	 * @var int
	 */
	protected $timeLimit = 604800;

	protected function main(){

		$this->getEmailRecord()
		->validateExpiration()
		->activateUser()
		->setReturn();
	}

	/**
	 * Select one row from EMAILS table
	 *
	 * @return $this
	 */
	protected function getEmailRecord(){
		$this->aEmail = $this->Registry->Mongo->EMAILS->findOne(array('_id' => $this->Request['eid'], 'code' =>  $this->Request['hash']));
		if(empty($this->aEmail)){
			/**
			 * @todo
			 * Translate string
			 */
			throw new \Lampcms\Exception($this->_('Unable to find user') );
		}

		d('$this->aEmail: '.print_r($this->aEmail, 1));

		return $this;
	}


	/**
	 * Make sure that validation code is not
	 * expired
	 *
	 * @todo need to generate new validation code and re-email it
	 * to the same user
	 *
	 * @return object $this
	 */
	protected function validateExpiration(){
		if( ($this->aEmail['i_code_ts'] + $this->timeLimit) < time()){
			/**
			 * @todo translate string
			 */
			throw new \Lampcms\NoticeException($this->_('Activation code no longer valid') );
		}

		return $this;
	}


	/**
	 * Change user's user_group_id to registered
	 * and set validation_time to now in EMAILS record
	 *
	 * @return object $this
	 */
	protected function activateUser(){

		$aUser = $this->Registry->Mongo->USERS->findOne(array('_id' => (int)$this->aEmail['i_uid']) );

		if(empty($aUser)){
			/**
			 * @todo translate string
			 */
			throw new \Lampcms\Exception($this->_('Unable to find user, please create a new account') );
		}
		

		$this->oActivatedUser = User::factory($this->Registry, $aUser);
		$role = $this->oActivatedUser->getRoleId();
		/**
		 * If User's role is NOT 'unactivated' then
		 * throw an exception
		 */
		if( false === \strstr($role, 'unactivated')){
			
			/**
			 * @todo
			 * Translate string
			 */
			throw new \Lampcms\NoticeException($this->_('This account has already been activated') );
		}
		
		$this->oActivatedUser->activate()->save();

		/**
		 * Now IF Viewer is actually the user that was just activated
		 * we must also update the Viewer!
		 * If we don't then the Viewer object is not updated
		 * and the Viewer in session is still unactivated
		 */
		if($this->Registry->Viewer->equals($this->oActivatedUser)){
			$this->processLogin($this->oActivatedUser);
		}

		$this->Registry->Dispatcher->post($this->oActivatedUser, 'onUserActivated');

		$this->aEmail['i_vts'] = time();
		$this->Registry->Mongo->EMAILS->save($this->aEmail);

		return $this;
	}

	/**
	 * @todo translate string
	 *
	 */
	protected function setReturn(){

		$this->aPageVars['title'] = 'Account activation complete';

		$this->aPageVars['body'] = '<div id="tools" class="larger">Account activation complete<br/>The account <b>'.$this->oActivatedUser->username.'</b> now has all the privileges<br/>
		of a registered user on our website.<br/>
		<br/>If you not already logged in, please login using<br/>
		the form above</div>';
	}


	protected function sendNewCode(){

	}
}
