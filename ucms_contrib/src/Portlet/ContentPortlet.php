<?php

namespace MakinaCorpus\Ucms\Contrib\Portlet;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use MakinaCorpus\Ucms\Contrib\TypeHandler;
use MakinaCorpus\Ucms\Dashboard\Action\ActionProviderInterface;
use MakinaCorpus\Ucms\Dashboard\Portlet\AbstractAdminPortlet;
use MakinaCorpus\Ucms\Dashboard\Page\DatasourceInterface;
use MakinaCorpus\Ucms\Dashboard\Page\PageState;

class ContentPortlet extends AbstractAdminPortlet
{
    use StringTranslationTrait;

    /**
     * @var ActionProviderInterface
     */
    private $actionProvider;

    /**
     * @var \MakinaCorpus\Ucms\Contrib\TypeHandler
     */
    private $typeHandler;

    /**
     * Default constructor
     *
     * @param DatasourceInterface $datasource
     * @param ActionProviderInterface $actionProvider
     * @param \MakinaCorpus\Ucms\Contrib\TypeHandler $typeHandler
     */
    public function __construct(
        DatasourceInterface $datasource,
        ActionProviderInterface $actionProvider,
        TypeHandler $typeHandler
    ) {
        parent::__construct($datasource);

        $this->actionProvider = $actionProvider;
        $this->typeHandler = $typeHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle()
    {
        return $this->t("Content");
    }

    /**
     * {@inheritDoc}
     */
    public function getPath()
    {
        return 'admin/dashboard/content';
    }

    /**
     * {@inheritDoc}
     */
    public function getActions()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function renderActions()
    {
        $build = [];

        // FIXME, allow to have multiple action groups
        $build['editorial'] = [
          '#theme'      => 'ucms_dashboard_actions',
          '#actions'    => $this->actionProvider->getActions('editorial'),
          '#show_title' => true,
          '#title'      => $this->t("Create content"),
        ];
        $build['component'] = [
          '#theme'      => 'ucms_dashboard_actions',
          '#actions'    => $this->actionProvider->getActions('component'),
          '#show_title' => true,
          '#title'      => $this->t("Create component"),
        ];

        return $build;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDisplay(&$query, PageState $pageState)
    {
        $query['type'] = $this->typeHandler->getEditorialContentTypes();
        $query['is_global'] = 0;

        return new NodePortletDisplay($this->t("You have no content yet."));
    }

    /**
     * {@inheritDoc}
     */
    public function userIsAllowed(AccountInterface $account)
    {
        return true;
    }
}
