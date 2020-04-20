<?php
/**
 * 
 * @file
 * @author Antoine Mercier-Linteau
 * @license GNU
 */

namespace MediaWiki\Extension\TaskManager;

//use SMWQueryProcessor;
//use SMW\ApplicationFactory;

/**
 * Allows a user to browse all their assigned tasks.
 * @ingroup SpecialPage
 */
class SpecialMyTasks extends \SpecialPage
{	
	/**
	 * @inheritdoc
	 * */
	public function __construct()
	{
		parent::__construct('MyTasks', 'edit');
        
		$this->addHelpLink(\Title::newFromText('Tâches', NS_HELP)->getFullURL(), true);
	}
	
	/**
	 * @inheritdoc
	 * */
	public function execute($params)
	{
	    
	    // Get all the tasks assigned to this user.
	    /*$query = SMWQueryProcessor::createQuery(
	        '[[Catégorie:Tâches]][[Assignee::Utilisateur:'.$this->getUser()->getName().']]',
	        \SMWQueryProcessor::getProcessedParams([
	            'limit' => 500
	        ]),
	        SMWQueryProcessor::INLINE_QUERY,
	        'list'
        );
	    
	    $querySourceFactory = ApplicationFactory::getInstance()->getQuerySourceFactory();
	    $querySource = $querySourceFactory->get();
	    $result = $querySource->getQueryResult( $query );*/
	    
	    $this->getOutput()->setPageTitle('Mes tâches');
	    
	    $request = $this->getRequest()->getQueryValues();
	    $user = isset($request['user']) ? $request['user']: $this->getUser()->getName();
	    
	    if(\User::newFromName($user)->isAnon()) // If the user requested does not exists.
	    {
	        $this->getOutput()->addWikiTextAsInterface('<div class="error">L\'utilisateur n\'existe pas.</div>');
	        return;
	    }
	    
	    $this->getOutput()->addWikiTextAsInterface("{{Toutes_les_tâches_d'un utilisateur|utilisateur=$user}}");
	}
}
