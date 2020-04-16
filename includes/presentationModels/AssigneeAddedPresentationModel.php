<?php
/**
 * TaskManager AssigneeAddedPresentationModel.
 *
 * @file
 * @ingroup Extensions
 * @author Antoine Mercier-Linteau
 * @license gpl-2.0
 */

namespace MediaWiki\Extension\TaskManager;

/**
 * Defines a presentation model for the task-manager-assignee-added event.
 * */
class AssigneeAddedPresentationModel extends \EchoEventPresentationModel
{
    /**
     * @param \EchoEvent $event
     * @return array
     */
    public static function locateNewAssignees(\EchoEvent $event)
    {
        $users = [];
        
        foreach($event->getExtraParam('new-assignees', []) as $id)
        {
            $users[] = \User::newFromId($id);
        }
        
        return $users;
    }

    /**
     * @inheritdoc
     * */
    public function canRender() 
    {
        $event = $this->event;
        $title = $this->event->getTitle();
        
        if(!$title || !$title->exists()) { return false; } // Page does not exist for some reason.
        if($event->getAgent() === null) { return false; } // No idea why agent would be null, but let's account for that.
        
        $page = \WikiPage::factory($title);
        
        if($page->getRevision()->getId() == $event->getExtraParam('revision-id'))
        {
            return true; // The page did not change, the notification can proceed.
        }
        
        /* Check if the user is still part of the assignees ...
         * sort of, to make this less complicated, it just checks if the user name is mentionned in the whole page...
         */
        $content = $page->getContent(\Revision::FOR_THIS_USER)->getNativeData();
        
        if(strpos($content, $this->getUser()->getName()) === false)
        {
            return false; // The user name was not found in the page ... it must have been unassigned.
        }
        
        
        // Add the just assigned task ( and its talk page) to the assignee's watchlist.
        $watchlist = \MediaWiki\MediaWikiServices::getInstance()->getWatchedItemStore();
        $watchlist->addWatch($this->getUser(), $title);
        // Normally talk pages are defined.
        if($talk = $title->getTalkPageIfDefined()) { $watchlist->addWatch($this->getUser(), $talk); }
        
        return true; // Let the event through.
    }
    
    /**
     * @inheritdoc
     */
    public function getHeaderMessage()
    {
        return new \Message($this->getHeaderMessageKey(), [$this->event->getTitle()->getFullText()]);
    }
    
    /**
     * @inheritdoc
     * */
    public function getIconType() { return 'list'; }
    
    /**
     * @inheritdoc
     * */
    public function getPrimaryLink() 
    {
        // Return the url to the task on which the assignee was added.
        return ['url' => $this->event->getTitle()->getFullURL(), 'label' => $this->event->getTitle()->getFullText()];
    }
    
    /**
     * @inheritdoc
     * */
    public function getSecondaryLinks()
    {
        $page = \WikiPage::factory($this->event->getTitle());
        $user = \User::newFromId($page->getRevision()->getUser(\Revision::FOR_THIS_USER));
        
        return [
            [   // The user that did the assignation.
                'url' => $user->getUserPage()->getFullURL(),
                'label' => $user->getName(),
                'icon' => 'userAvatar'
            ]
        ];
    }
}