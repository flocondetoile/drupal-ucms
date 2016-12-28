<?php

namespace MakinaCorpus\Ucms\Notification\Formatter;

use MakinaCorpus\APubSub\Notification\NotificationInterface;
use MakinaCorpus\Drupal\APubSub\Notification\AbstractNotificationFormatter;
use MakinaCorpus\Ucms\Contrib\ContentTypeManager;

abstract class AbstractContentNotificationFormatter extends AbstractNotificationFormatter
{
    /**
     * @var ContentTypeManager
     */
    private $contentTypeManager;

    /**
     * {@inheritdoc}
     */
    public function getURI(NotificationInterface $interface)
    {
        $contentIdList = $interface->getResourceIdList();
        if (count($contentIdList) === 1) {
            return 'node/'.reset($contentIdList);
        }
    }

    /**
     * @param ContentTypeManager $contentTypeManager
     */
    public function setTypeManager($contentTypeManager)
    {
        $this->contentTypeManager = $contentTypeManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTitles($idList)
    {
        $titles = [];

        foreach (node_load_multiple($idList) as $node) {
            if (!empty($node->title)) {
                $titles[$node->nid] = $node->title;
            }
        }

        return $titles;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTypeLabelVariations($count)
    {
        return ["@count content", "@count contents"];
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareImageURI(NotificationInterface $notification)
    {
        $contentIdList = $notification->getResourceIdList();
        if (count($contentIdList) === 1) {
            if ($node = node_load(reset($contentIdList))) {
                return in_array($node->type, $this->contentTypeManager->getNonMediaTypes()) ? "file" : "picture";
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getVariations(NotificationInterface $notification,array &$args = [])
    {
        $idList = $notification->getResourceIdList();
        // This is already cached by $this->getTitles()
        foreach (node_load_multiple($idList) as $node) {
            $args['@type'] = t(node_type_get_name($node));
        }
    }

    function getTranslations()
    {
        $this->formatPlural(1, "@count content", "@count contents");
    }
}
