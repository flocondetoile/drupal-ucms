<?php

namespace MakinaCorpus\Ucms\Layout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use MakinaCorpus\Ucms\Layout\Layout;
use MakinaCorpus\Ucms\Layout\StorageInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

class LayoutEditForm extends FormBase
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * {inheritdoc}
     */
    static public function create(ContainerInterface $container)
    {
        return new self($container->get('ucms_layout.storage'));
    }

    /**
     * Default constructor
     *
     * @param StorageInterface $storage
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * {inheritdoc}
     */
    public function getFormId()
    {
        return 'ucms_layout_edit_form';
    }

    /**
     * {inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Layout $layout = null)
    {
        if (!$layout) {
            return $form;
        }

        $form['#layout_id'] = $layout->getId();

        $form['title'] = [
            '#title'          => t("Page title"),
            '#type'           => 'textfield',
            '#required'       => true,
            '#default_value'  => $layout->getTitle(),
            '#description'    => t("The page title that will be displayed on frontend."),
        ];

        $form['title_admin'] = [
            '#title'          => t("Title for administration"),
            '#type'           => 'textfield',
            '#required'       => true,
            '#default_value'  => $layout->getAdminTitle(),
            '#description'    => t("This title will used for administrative pages and will never be shown to end users."),
        ];

        /*
        if (module_exists('path')) {
            $form['path_alias'] = [
                '#title'          => t("URL alias"),
                '#field_prefix'   => url('/', ['absolute' => true]),
                '#type'           => 'textfield',
                '#description'    => t("You change here the URL of your page."),
                '#default_value'  => drupal_get_path_alias('layout/' . $layout->getId()),
            ];
        }
         */

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type'   => 'submit',
            '#value'  => t("Update"),
            '#submit' => ['::submitForm'],
        ];
        $form['actions']['delete'] = [
            '#type'   => 'submit',
            '#value'  => t("Delete"),
            '#submit' => ['::deleteSubmit'],
            '#limit_validation_errors' => [],
        ];

        return $form;
    }

    /**
     * Delete button click
     */
    public function deleteSubmit(array &$form, FormStateInterface $form_state)
    {
        // Else drupal_goto() will force the destination parameter to override us.
        $destination = drupal_get_destination();
        unset($_GET['destination']);
        $form_state->setRedirect('layout/' . $form['#layout_id'] . '/delete', ['query' => $destination]);
    }

    /**
     * {inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $layout = $this
          ->storage
          ->load($form['#layout_id'])
          ->setAdminTitle($form_state->getValue('title_admin'))
          ->setTitle($form_state->getValue('title'))
          ->setSiteId(0) // @todo
        ;

        $this->storage->save($layout);

        drupal_set_message(t("Page %page has been updated.", ['%page' => $layout->getAdminTitle()]));

        $form_state->setRedirect('layout/' . $layout->getId());
    }
}
