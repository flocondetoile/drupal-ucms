<?php


namespace MakinaCorpus\Ucms\Label\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use MakinaCorpus\APubSub\Notification\EventDispatcher\ResourceEvent;
use MakinaCorpus\Ucms\Label\LabelManager;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * Label creation and edition form
 */
class LabelEdit extends FormBase
{
    /**
     * {@inheritdoc}
     */
    static public function create(ContainerInterface $container)
    {
        return new self(
            $container->get('ucms_label.manager'),
            $container->get('event_dispatcher')
        );
    }


    /**
     * @var LabelManager
     */
    protected $manager;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;


    public function __construct(LabelManager $manager, EventDispatcherInterface $dispatcher)
    {
        $this->manager = $manager;
        $this->dispatcher = $dispatcher;
    }


    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ucms_label_edit';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, \stdClass $label = null)
    {
        if ($label === null) {
            $label = new \stdClass();
        }

        $form_state->setTemporaryValue('label', $label);

        $form['#form_horizontal'] = true;

        $form['name'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Name'),
            '#default_value' => isset($label->name) ? $label->name : '',
            '#maxlength' => 255,
            '#required' => true,
            '#weight' => -5,
        );

//        $form['description'] = array(
//            '#type' => 'text_format',
//            '#title' => $this->t('Description'),
//            '#default_value' => isset($label->description) ? $label->description : '',
//            '#format' => isset($label->format) ? $label->format : '',
//            '#weight' => 0,
//        );

        // taxonomy_get_tree and taxonomy_get_parents may contain large numbers of
        // items so we check for taxonomy_override_selector before loading the
        // full vocabulary. Contrib modules can then intercept before
        // hook_form_alter to provide scalable alternatives.
        if (!variable_get('taxonomy_override_selector', FALSE)) {
            $has_children = false;

            if (isset($label->tid)) {
                $parent = $this->manager->loadParent($label);
                $has_children = $this->manager->hasChildren($label);
            }

            $options = [];
            foreach ($this->manager->loadRootLabels() as $root_label) {
                if (!isset($label->tid) || $label->tid != $root_label->tid) {
                    $options[$root_label->tid] = $root_label->name;
                }
            }

            $form['parent'] = array(
                '#type' => 'select',
                '#title' => $this->t('Parent label'),
                '#options' => $options,
                '#empty_value' => '0',
                '#empty_option' => $this->t("- None -"),
                '#default_value' => !empty($parent) ? $parent->tid : null,
                '#multiple' => false,
            );

            if ($has_children) {
                $form['parent']['#disabled'] = true;
                $form['parent']['#description'] = $this->t("You must move or delete the children labels if you want to define a parent label for this one.");
            }
        }

        if ($this->manager->canEditLockedLabels()) {
            $form['locked'] = array(
                '#type' => 'checkbox',
                '#title' => $this->t('Non editable label'),
                '#default_value' => isset($label->is_locked) ? $label->is_locked : 0,
            );

            if (!$this->manager->canEditNonLockedLabels()) {
                $form['locked']['#disabled'] = true;
                $form['locked']['#default_value'] = 1;
            }
        }

        $form['actions'] = array(
            '#type' => 'actions',
        );
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );

        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        try {
            $label = $form_state->getTemporaryValue('label');
            $label->name = $form_state->getValue('name');
            $label->is_locked = ($form_state->getValue('locked') === null) ? 0 : $form_state->getValue('locked');
            $label->parent = ($parent = $form_state->getValue('parent')) ? $parent : 0;
            $label->vid = $this->manager->getVocabularyId();
            $label->vocabulary_machine_name = $this->manager->getVocabularyMachineName();

            $op = $this->manager->saveLabel($label);

            if ($op == SAVED_NEW) {
                drupal_set_message($this->t("The new \"@name\" label has been created.", array('@name' => $label->name)));
                $this->dispatcher->dispatch('label:add', new ResourceEvent('label', $label->tid, $this->currentUser()->uid));
            } else {
                drupal_set_message($this->t("The \"@name\" label has been updated.", array('@name' => $label->name)));
                $this->dispatcher->dispatch('label:edit', new ResourceEvent('label', $label->tid, $this->currentUser()->uid));
            }
        }
        catch (\Exception $e) {
            drupal_set_message($this->t("An error occured during the edition of the \"@name\" label. Please try again.", array('@name' => $label->name)), 'error');
        }

        $form_state->setRedirect('admin/dashboard/label');
    }

}
