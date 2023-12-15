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

        return true; // Let the event through.
    }
    
    /**
     * Adds the page that triggered the event to the user's watchlist.
     * */
    private function _addToWatchlist()
    {
        // Add the just assigned task ( and its talk page) to the assignee's watchlist.
        $watchlist = \MediaWiki\MediaWikiServices::getInstance()->getWatchedItemStore();
        $watchlist->addWatch($this->getUser(), $this->event->getTitle());
        // Normally talk pages are defined.
        if($talk = $this->event->getTitle()->getTalkPageIfDefined()) { $watchlist->addWatch($this->getUser(), $talk); }
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
        $user = \User::newFromId($page->getRevisionRecord()->getUser());
        
        return [
            [   // The user that did the assignation.
                'url' => $user->getUserPage()->getFullURL(),
                'label' => $user->getName(),
                'icon' => 'userAvatar'
            ]
        ];
    }
}