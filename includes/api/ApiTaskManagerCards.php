<?php
/**
 * API module that returns rendered "cards" for a list of task titles.
 *
 * For each requested title, this module checks it against $wgTaskManagerLinkPatterns
 * and, if a pattern matches and the requesting user can read the target page,
 * expands the configured wrap template and returns the resulting HTML. The
 * response is keyed by the title the caller asked about (not the resolved one),
 * so callers can map results back to their input.
 *
 * Consumers:
 *  - ext.taskmanager.enrich (web): swaps plain task links with cards on every load.
 *  - Mobile app: same payload, used to render task cards inline.
 *
 * @file
 * @ingroup Extensions
 * @author Antoine Mercier-Linteau
 * @license gpl-2.0
 */

namespace MediaWiki\Extension\TaskManager;

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiTaskManagerCards extends ApiBase
{
    public function execute()
    {
        $names = $this->getParameter('titles');
        $pageParam = $this->getParameter('page');
        $patterns = $this->getConfig()->get('TaskManagerLinkPatterns');

        $cards = [];

        if(empty($patterns) || empty($names))
        {
            $this->getResult()->addValue(null, 'cards', (object)$cards);
            return;
        }

        $services = MediaWikiServices::getInstance();
        $parser = $services->getParserFactory()->create();
        $user = $this->getUser();

        // Parse context = the page the link appears on, NOT the task page itself.
        // Otherwise the template's own [[TaskPage]] reference becomes a self-link
        // (no href, .mw-selflink). The old InternalParseBeforeLinks hook avoided
        // this because it ran inside the containing page's parse.
        $contextTitle = $pageParam !== null && $pageParam !== ''
            ? Title::newFromText($pageParam) : null;
        if(!$contextTitle || !$contextTitle->canExist()) { $contextTitle = Title::newMainPage(); }

        foreach($names as $name)
        {
            $title = Title::newFromText($name);
            if(!$title || !$title->canExist()) { continue; }

            $title = TaskManager::resolveRedirect($title);
            if(!$title->exists()) { continue; }
            if(!TaskManager::userCanRead($title, $user)) { continue; }

            foreach($patterns as $entry)
            {
                if(empty($entry['pattern']) || empty($entry['template'])) { continue; }
                if(!@preg_match($entry['pattern'], $title->getPrefixedText())) { continue; }
                if(!empty($entry['category']) && !TaskManager::titleHasCategory($title, $entry['category'])) { continue; }

                $resolvedName = $title->getPrefixedText();
                $arg = isset($entry['argument']) && $entry['argument'] !== ''
                    ? $entry['argument'].'='.$resolvedName
                    : $resolvedName;
                $wikitext = '{{'.$entry['template'].'|'.$arg.'}}';

                $opts = ParserOptions::newFromContext($this);
                $parserOutput = $parser->parse($wikitext, $contextTitle, $opts);
                $html = $parserOutput->getText([
                    'enableSectionEditLinks' => false,
                    'unwrap' => true
                ]);

                // Strip a lone <p>...</p> wrapper added by the parser when the
                // template emits inline content, so the result can be substituted
                // directly in place of an <a> element without invalid nesting.
                $trimmed = trim($html);
                if(preg_match('/^<p>(.*)<\/p>$/s', $trimmed, $m)) { $html = $m[1]; }

                $cards[$name] = $html;
                break;
            }
        }

        $this->getResult()->addValue(null, 'cards', (object)$cards);
    }

    public function getAllowedParams()
    {
        return [
            'titles' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_ISMULTI => true,
                ParamValidator::PARAM_REQUIRED => true,
                ParamValidator::PARAM_ISMULTI_LIMIT1 => 100,
                ParamValidator::PARAM_ISMULTI_LIMIT2 => 200
            ],
            'page' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ]
        ];
    }

    public function isReadMode() { return true; }
    public function isWriteMode() { return false; }
    public function mustBePosted() { return false; }

    protected function getExamplesMessages()
    {
        return [
            'action=taskmanager-cards&titles=Gestion:T%C3%A2ches/Liste/1|Gestion:T%C3%A2ches/Liste/2'
                => 'apihelp-taskmanager-cards-example-1'
        ];
    }
}
