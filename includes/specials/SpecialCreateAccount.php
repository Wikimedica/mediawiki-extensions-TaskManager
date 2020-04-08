<?php
/**
 * WikimedicaAccount extension SpecialCreateAccount page override.
 *
 * @file
 * @ingroup Extensions
 * @author Antoine Mercier-Linteau
 * @license proprietary
 */

namespace MediaWiki\Extension\WikimedicaAccount;

/**
 * SpecialCreateAccount page override to prevent logged in users from creating accounts and do proper redirections when doing proxy account creation.
 * @ingroup SpecialPage
 */
class SpecialCreateAccount extends \SpecialCreateAccount
{
	protected $targetName;
	
	/**
	 * @inheritdoc
	 * */
	public function __construct()
	{
		parent::__construct();
		$this->mRestriction = 'sysop';
		$this->addHelpLink(\Title::newFromText('Inscription', NS_HELP)->getFullURL(), true);
	}
	
	/**
	 * @inheritdoc
	 * */
	public function isRestricted()
	{
		return !in_array( 'sysop', $this->getUser()->getEffectiveGroups()) || !$this->getUser()->isAnon();
	}
	
	/**
	 * @inheritdoc
	 * */
	public function userCanExecute( \User $user )
	{
		return in_array( 'sysop', $user->getEffectiveGroups()) || $user->isAnon();
	}
	
	/**
	 * @inheritdoc
	 * */
	protected function successfulAction( $direct = false, $extraMessages = null ) 
	{
		parent::successfulAction($direct, $extraMessages);
		
		if(!$this->getUser()->isAnon() && in_array( 'sysop', $this->getUser()->getEffectiveGroups()) && $this->targetUser) // If this is a proxy account creation.
		{
			/* Redirect to SpecialChangeProfessionnalInformation. The account has been created at that point, but we
			 * can trust an admin to correcly fill the professionnal information. */
			$this->getOutput()->redirect(
				\Title::newFromText('ChangeProfessionnalInformation', NS_SPECIAL)->getLinkURL([
					'action' => 'create', 
					'target' => $this->targetUser->getName()]
				)
			);
		}
	}
}
