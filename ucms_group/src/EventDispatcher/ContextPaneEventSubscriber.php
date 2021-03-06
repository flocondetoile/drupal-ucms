<?php

namespace MakinaCorpus\Ucms\Group\EventDispatcher;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use MakinaCorpus\Calista\Action\Action;
use MakinaCorpus\Calista\Action\ActionRegistry;
use MakinaCorpus\Drupal\Calista\EventDispatcher\ContextPaneEvent;
use MakinaCorpus\Ucms\Group\GroupAccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContextPaneEventSubscriber implements EventSubscriberInterface
{
    use StringTranslationTrait;

    /**
     * @var AccountInterface
     */
    private $account;

    /**
     * @var ActionRegistry
     */
    private $actionRegistry;

    /**
     * Default constructor
     *
     * @param ActionRegistry $actionRegistry
     */
    public function __construct(AccountInterface $account, ActionRegistry $actionRegistry)
    {
        $this->account = $account;
        $this->actionRegistry = $actionRegistry;
    }

    public static function getSubscribedEvents()
    {
        return [
            ContextPaneEvent::EVENT_INIT => [
                ['onContextPaneInit', 10]
            ],
        ];
    }

    public function onContextPaneInit(ContextPaneEvent $event)
    {
        switch (current_path()) {

            case 'admin/dashboard/group':
            case 'admin/dashboard/group/mine':
            case 'admin/dashboard/group/all':
                if ($this->account->hasPermission(GroupAccess::PERM_MANAGE_ALL)) {
                    $event->getContextPane()->addActions([
                        new Action($this->t("Add group"), 'admin/dashboard/group/add', null, 'plus', 0, true, true),
                    ]);
                }
                break;

            default:
                if ('admin/dashboard/group/' === substr(current_path(), 0, 22)) {
                    if ($group = menu_get_object('ucms_group_menu', 3)) {
                        $event->getContextPane()->addActions(
                            $this->actionRegistry->getActions($group)
                        );
                    }
                }
                break;
        }
    }
}
