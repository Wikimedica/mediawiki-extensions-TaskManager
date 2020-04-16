<?php
/**
 * TaskManager extension main class.
 *
 * @file
 * @ingroup Extensions
 * @author Antoine Mercier-Linteau
 * @license gpl-2.0
 */

namespace MediaWiki\Extension\TaskManager;

use DTPageStructure;
use Flow;

/**
 * TaskManager extension class.
 */
class TaskManager 
{
    /** @staticvar boolean if this hook was ran. */
    private static $_ran = false;
    
    /**
     * Triggers an event when a task page is modified.
     * */
    public static function onPageContentSave( \WikiPage &$wikiPage, &$user, &$content, &$summary, $isMinor, $isWatch, $section, &$flags, &$status )
    {
        if(self::$_ran) { return; } // Can only be called once.
        self::$_ran = true; // We don't want to send notifications twice.
        
        $isTask = false;
        foreach($wikiPage->getCategories() as $cat) // Check if the page is a task.
        {
            if($isTask = in_array(strtolower($cat->getDbKey()), ['tasks', 'tâches'])) { break; }
        }
        
        if(!$isTask) { return; } // Ignoring, page is not a task.
        
        $title = $wikiPage->getTitle();
        
        $previousPageStructure = $title->exists() ? DTPageStructure::newFromTitle($title): null;
        $newPageStructure = new DTPageStructure();
        $newPageStructure->parsePageContents(\ContentHandler::getContentText($content));
        $previousParams = self::_getTaskTemplate($previousPageStructure);
        $newParams = self::_getTaskTemplate($newPageStructure);
        
        $diff = self::_diff($previousParams, $newParams);
        
        if(!isset($diff['assignees'])) { return; } // assignees did not change.
        
        $newAssignees = array_diff(
            explode(',', $diff['assignees']), 
            isset($previousParams['assignees']) ? explode(',', $previousParams['assignees']): []
        );
        
        $userIds = $users = [];
        
        foreach($newAssignees as $new)
        {
            if(!$u = \User::newFromName($new)) { continue; } // Invalid user.
            
            if($u->isAnon() || $u->isLocked()) { continue; } // User does not exist or has been locked.
            
            // Do not send a notification if a user added himself.
            if($u->getId() == $user->getId()) { continue; }
            
            $userIds[] = $u->getId();
            $users[] = $u;
        }
        
        if(!$users) { return; } // If no valid users were found.
        
        // Create the event.
        \EchoEvent::create([
            'type' => 'task-manager-assignee-added',
            'title' => $title,
            'extra' => [
                'new-assignees' => $userIds,
                'revision-id' => $wikiPage->getRevision()->getId() /* Used to confirm the page did not change between the time
                a user is getting the notification and the time it was triggered. If the page changed, make sure it still exists
                and the user is still part of the assignees. */
            ],
            'agent' => $user
        ]);
    }
    
    /**
     * Extracts the task template from a page.
     * @param \DTPageStructure $page the page to extract the template from.
     * @return array the template [parameter => value]
     * */
    private static function _getTaskTemplate($page)
    {
        if(get_class($page) !== 'DTPageStructure') { return $page; }
        
        foreach($page->mComponents as $c) // Looking for the Task template.
        {
            if(!$c->mIsTemplate) { continue; } // Not a template, skip
            
            if(in_array(strtolower($c->mTemplateName), ['task', 'tâche']))
            {
                return array_change_key_case($c->mFields, CASE_LOWER); // Found the Task template.
            }
        }
        
        return [];
    }
    
    /**
     * Extracts the parameters that have been modified between two templates.
     * @param array $t1
     * ­@param array $t2
     * @return array [parameter => new_value]
     * */
    private static function _diff($t1, $t2)
    {
        if(!$t1) { return $t2; } // $t2 contains all new parameters.
        
        $diff = $t2;
        
        foreach($t1 as $k => $v) // Check each parameter and remove those that have not changed.
        {
            if(isset($t2[$k]) && $t1[$k] == $t2[$k])
            {
                unset($diff[$k]); // This parameter did not change between the two versions.
            }
        }
        
        return $diff;
    }
    
    /**
     * Allows registration of custom Echo events
     * @param array $echoNotifications for custom Echo event
     * @param array $echoNotificationCategories for custom Echo categories
     * @param array $echoNotiticationIcons replace or add custom icons
     * @return bool
     */
    public static function onBeforeCreateEchoEvent( &$echoNotifications, &$echoNotificationCategories, &$echoNotificationIcons )
    {
        // Not needed for now, the system category is fine (forces web notifications on).
        $echoNotificationCategories['task-manager-assignee-added'] = [
            'tooltip' => 'echo-pref-tooltip-task-manager-assignee-added',
            'priority' => 2, // High priority.
            'no-dismiss' => ['web']
        ];
        
        // Enable email alerts by default.
        global $wgDefaultUserOptions;
        $wgDefaultUserOptions["echo-subscriptions-email-task-manager-assignee-added"] = true;
        $wgDefaultUserOptions["echo-subscriptions-web-task-manager-assignee-added"] = true;
        
        global $wgNotifyTypeAvailabilityByCategory; // Allow users to control notifications.
        $wgNotifyTypeAvailabilityByCategory['task-manager-assignee-added'] = ['web' => true, 'email' => true];
        
        global $wgEchoUseJobQueue;
        $wgEchoUseJobQueue = !(defined('ENVIRONMENT') && ENVIRONMENT == 'development'); // Use the job queue if not in a development environment.
        
        $echoNotifications['task-manager-assignee-added'] = [
            'category' => 'task-manager-assignee-added',
            'section' => 'alert',
            'group' => 'interactive',
            'presentation-model' => \MediaWiki\Extension\TaskManager\AssigneeAddedPresentationModel::class,
            'user-locators' => ['\MediaWiki\Extension\TaskManager\AssigneeAddedPresentationModel::locateNewAssignees'],
            'immediate' => defined(ENVIRONMENT) && ENVIRONMENT == 'development' // Use the job queue if not in a development environment.
        ];
        
        $echoNotificationIcons['list']['path'] = 'TaskManager/modules/icons/list.svg';
    }
    
    /**
     * PersonalUrls hook handler.
     *
     * @param array &$personalUrls
     * @param Title &$title (unused)
     * @param Skin $skin
     * @return bool true
     */
    public static function onPersonalUrls(&$personalUrls, &$title, $skin)
    {
        // Do not show for anonymous users.
        if($skin->getUser()->isAnon()) { return true; }
        
        
        $newPersonalUrls = [];
        
        $link = [
            'id' => 'pt-tasks',
            'text' => 'Tâches',//$skin->msg( 'taskManager-link-label' )->text(),
            'title' => 'Mes tâches',//$skin->msg( 'taskManager-link-title' )->text(),
            'href' => \SpecialPage::getSafeTitleFor('MyTasks')->getLocalURL(['user' => $skin->getUser()->getName()]),
            'exists' => true,
        ];
        
        // Insert our link before the link to user preferences.
        // If the link to preferences is missing, insert at the end.
        foreach($personalUrls as $key => $value)
        {
            if($key === 'preferences') { $newPersonalUrls['tasks'] = $link; }
            $newPersonalUrls[$key] = $value;
        }
        
        if (!array_key_exists('tasks', $newPersonalUrls))
        {
            $newPersonalUrls['tasks'] = $link;
        }
        
        $personalUrls = $newPersonalUrls;
        
        return true;
    }
	
	/** 
	 * Redirect Topic page to discussion page or to task pages when dealing with tasks when reaching them through notifications. 
	 * */
	public static function onArticleFromTitle( &$title, &$page, $context)
	{
	    if($title->getNamespace() != NS_TOPIC) { return; }
	    
	    $queryValues = $context->getRequest()->getQueryValues();
	    $query = [];
	    
	    // Only redirect when reaching Topics through notifications.
	    if((!isset($queryValues['fromnotif']) || $queryValues['fromnotif'] != '1') && !isset($queryValues['markasread']) ) { return true; }
	    
	    
	    // Retrieve the board associated with the topic.
	    $storage = Flow\Container::get( 'storage.workflow' );
	    $uuid = Flow\WorkflowLoaderFactory::uuidFromTitle( $title );
	    $workflow = $storage->get( $uuid );
	    if ( !$workflow ) { return; }
	    $board = $workflow->getOwnerTitle();
	    
	    $subject = $board; // Redirect to the board.
	    
	    // Check if the subject is a task.
	    foreach((\Article::newFromTitle($subject->getOtherPage(), $context))->getCategories() as $category)
	    {
	        if($category->getDBKey() == 'Tâches')
	        {
	            $subject = $board->getOtherPage(); // Get the subject page of the talk page.
	            break;
	        }
	    }
	    
	    $subject->setFragment($title->getFragment()); // Set fragment to that of the topic shown.
	    
	    if(isset($queryValues['action']))
	    {
	        switch($queryValues['action'])
	        {
	            case 'history':
	            case 'compare-post-revisions':
	            case 'single-view':
	                return true; // Let action through without redirect.
	            default:
	        }
	    }
	    
	    if(isset($queryValues['topic_showPostId'])) { $query['topic_showPostId'] = $queryValues['topic_showPostId']; }
	    if(isset($queryValues['fromnotif'])) { $query['fromnotif'] = $queryValues['fromnotif']; }
	    if(isset($queryValues['markasread'])) { $query['markasread'] = $queryValues['markasread']; }
	    $context->getOutput()->redirect($subject->getFullURL($query));
	    
	    return true;
	}
}
