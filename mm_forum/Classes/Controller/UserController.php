<?php

/*                                                                      *
 *  COPYRIGHT NOTICE                                                    *
 *                                                                      *
 *  (c) 2010 Martin Helmich <m.helmich@mittwald.de>                     *
 *           Mittwald CM Service GmbH & Co KG                           *
 *           All rights reserved                                        *
 *                                                                      *
 *  This script is part of the TYPO3 project. The TYPO3 project is      *
 *  free software; you can redistribute it and/or modify                *
 *  it under the terms of the GNU General Public License as published   *
 *  by the Free Software Foundation; either version 2 of the License,   *
 *  or (at your option) any later version.                              *
 *                                                                      *
 *  The GNU General Public License can be found at                      *
 *  http://www.gnu.org/copyleft/gpl.html.                               *
 *                                                                      *
 *  This script is distributed in the hope that it will be useful,      *
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of      *
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the       *
 *  GNU General Public License for more details.                        *
 *                                                                      *
 *  This copyright notice MUST APPEAR in all copies of the script!      *
 *                                                                      */



	/**
	 *
	 * Controller for the User object. Offers user specific funcions, like user profiles, the
	 * user list, and (un)subscribe functionalities.
	 *
	 * @author     Martin Helmich <m.helmich@mittwald.de>
	 * @package    MmForum
	 * @subpackage Controller
	 * @version    $Id$
	 *
	 * @copyright  2010 Martin Helmich <m.helmich@mittwald.de>
	 *             Mittwald CM Service GmbH & Co. KG
	 *             http://www.mittwald.de
	 * @license    GNU Public License, version 2
	 *             http://opensource.org/licenses/gpl-license.php
	 * 
	 */

class Tx_MmForum_Controller_UserController
	extends Tx_MmForum_Controller_AbstractController {





		/*
		 * ATTRIBUTES
		 */





		/**
		 * The userfield repository.
		 * @var Tx_MmForum_Domain_Repository_User_UserfieldRepository
		 */
	protected $userfieldRepository = NULL;

		/**
		 * The topic repository
		 * @var Tx_MmForum_Domain_Repository_Forum_TopicRepository
		 */
	protected $topicRepository = NULL;



		/*
		 * DEPENDENCY INJECTORS
		 */
	
	public function injectTopicRepository(Tx_MmForum_Domain_Repository_Forum_TopicRepository $topicRepository) {
		$this->topicRepository = $topicRepository;
	}
	
	public function injectUserfieldRepository(Tx_MmForum_Domain_Repository_User_UserfieldRepository $userfieldRepository) {
		$this->userfieldRepository = $userfieldRepository;
	}





		/*
		 * ACTION METHODS
		 */





		/**
		 *
		 * Displays a list of all existing users.
		 *
		 * @param int $page The current page.
		 * @return void
		 *
		 */
	public function indexAction($page=1) {
		$this->view->assign('users', $this->frontendUserRepository->findForIndex((int)$this->localSettings['index']['pagebrowser']['itemsPerPage'], $page))
			->assign('page', $page)
			->assign('totalUserCount', $this->frontendUserRepository->countAll());
	}

		/**
		 *
		 * @param Tx_MmForum_Domain_Model_User_FrontendUser $user
		 * @param int $page
		 * @return void
		 *
		 */
	public function listPostsAction(Tx_MmForum_Domain_Model_User_FrontendUser $user=NULL, $page=1) {
		if($user === NULL) $user = $this->getCurrentUser();
		if($user === NULL) throw new Tx_MmForum_Domain_Exception_Authentication_NotLoggedInException (
			"You need to be logged in to view your own posts.", 1288084981 );
		$this->view->assign('topics', $this->topicRepository->findByPostAuthor($user, $page, (int)$this->localSettings['listPosts']['pagebrowser']['itemsPerPage']))
			->assign('user', $user)->assign('page', $page)
			->assign('totalTopicCount', $this->topicRepository->countByPostAuthor($user));
	}



		/**
		 *
		 * Displays a single user.
		 *
		 * @param Tx_MmForum_Domain_Model_User_FrontendUser $user
		 *                             The user whose profile is to be displayed.
		 * @return void
		 *
		 */

	public function showAction(Tx_MmForum_Domain_Model_User_FrontendUser $user) {
		$this->view->assign('user', $user)
			->assign('userfields', $this->userfieldRepository->findAll())
			->assign('topics', $this->topicRepository->findByPostAuthor($user,1,5));
	}



		/**
		 *
		 * Subscribes the current user to a forum or a topic.
		 *
		 * @param Tx_MmForum_Domain_Model_Forum_Forum $forum
		 *                             The forum that is to be subscribed. Either this
		 *                             value or the $topic parameter must be != NULL.
		 * @param Tx_MmForum_Domain_Model_Forum_Topic $topic
		 *                             The topic that is to be subscribed. Either this
		 *                             value or the $forum parameter must be != NULL.
		 * @param boolean $unsubscribe TRUE to unsubscribe the forum or topic instead.
		 * 
		 */

	public function subscribeAction ( Tx_MmForum_Domain_Model_Forum_Forum $forum=NULL,
	                                  Tx_MmForum_Domain_Model_Forum_Topic $topic=NULL,
	                                  $unsubscribe=FALSE ) {

			# Validate arguments
		if($forum === NULL && $topic === NULL)
			throw new Tx_Extbase_MVC_Exception_InvalidArgumentValue ("You need to subscribe a Forum or Topic!", 1285059341);

			# Create subscription
		$object =  $forum ? $forum : $topic;
		$user   =& $this->getCurrentUser();

		if($unsubscribe) $user->removeSubscription($object);
		else             $user->addSubscription($object);

			# Update user and redirect to subscription object.
		$this->frontendUserRepository->update($user);
		$this->flashMessages->add($this->getSubscriptionFlashMessage($object, $unsubscribe));
		$this->redirectToSubscriptionObject($object);
	}





		/*
		 * HELPER METHODS
		 */





		/**
		 *
		 * Redirects the user to the display view of a subscribeable object. This may
		 * either be a forum or a topic, so this method redirects either to the
		 * Forum->show or the Topic->show action.
		 *
		 * @param Tx_MmForum_Domain_Model_SubscribeableInterface $object
		 *                             A subscribeable object, i.e. either a forum or a
		 *                             topic.
		 * @return void
		 *
		 */

	protected function redirectToSubscriptionObject(Tx_MmForum_Domain_Model_SubscribeableInterface $object) {
		if($object instanceof Tx_MmForum_Domain_Model_Forum_Forum)
			$this->redirect ('show', 'Forum', NULL, array('forum' => $object));
		if($object instanceof Tx_MmForum_Domain_Model_Forum_Topic)
			$this->redirect ('show', 'Topic', NULL, array('topic' => $object));
	}


	
		/**
		 *
		 * Generates a flash message for when a subscription has successfully been
		 * created or removed.
		 *
		 * @return string A flash message.
		 *
		 */

	protected function getSubscriptionFlashMessage(Tx_MmForum_Domain_Model_SubscribeableInterface $object, $unsubscribe=FALSE) {
		$type = array_pop(explode('_',get_class($object)));
		$key  = 'User_'.($unsubscribe ? 'Uns' : 'S').'ubscribe_'.$type.'_Success';
		return Tx_Extbase_Utility_Localization::translate($key, 'MmForum', array($object->getTitle()));
	}
	
}
?>