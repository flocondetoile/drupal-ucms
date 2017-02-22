<?php

namespace MakinaCorpus\Ucms\Seo\EventDispatcher;

use MakinaCorpus\Ucms\Seo\SeoService;
use MakinaCorpus\Ucms\Site\EventDispatcher\SiteEvent;
use MakinaCorpus\Ucms\Site\EventDispatcher\SiteEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This subscriber will collect linked content within text fields.
 */
class SiteEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var SeoService
     */
    private $service;

    /**
     * Default constructor
     *
     * @param SeoService $service
     */
    public function __construct(SeoService $service)
    {
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    static public function getSubscribedEvents()
    {
        return [
            SiteEvents::EVENT_INIT => [
                ['onInit', 0]
            ],
            KernelEvents::TERMINATE => [
                ['onTerminate', 0]
            ],
        ];
    }

    /**
     * Site is being initialized, and hopefully at this stage current menu
     * item has never been loaded, so alter the current path to something
     * else, before Drupal router gets to us.
     */
    public function onInit(SiteEvent $event)
    {
        // Naive alias lookup for the current page
        $site = $event->getSite();
        $nodeId = $this->service->getAliasManager()->matchPath($_GET['q'], $site->getId());
        if ($nodeId) {
            $_GET['q'] = 'node/' . $nodeId;
        }

        // Set current context to the alias manager
        $this->service->getAliasCacheLookup()->setEnvironment($site->getId(), $_GET['q']);
    }

    /**
     * On terminate write cache.
     */
    public function onTerminate()
    {
        $this->service->getAliasCacheLookup()->write();
    }
}
