<?php

namespace Sidus\EAVModelBundle\Form\Type;

use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Exception\MissingFamilyException;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Model\FamilyInterface;
use Sidus\EAVModelBundle\Translator\TranslatableTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base form used for data edition
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class DataType extends AbstractType
{
    use TranslatableTrait;

    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var string */
    protected $collectionType;

    /** @var string */
    protected $dataClass;

    /**
     * @param FamilyRegistry $familyRegistry
     * @param string         $dataClass
     * @param string         $collectionType
     */
    public function __construct(
        FamilyRegistry $familyRegistry,
        $dataClass,
        $collectionType = 'collection'
    ) {
        $this->familyRegistry = $familyRegistry;
        $this->dataClass = $dataClass;
        $this->collectionType = $collectionType;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
                $form = $event->getForm();
                /** @var DataInterface $data */
                $data = $event->getData();

                if ($data) {
                    $family = $data->getFamily();
                } else {
                    $family = $options['family'];
                }

                if ($family) {
                    $this->buildValuesForm($form, $family, $data, $options);
                    $this->buildDataForm($form, $family, $data, $options);
                } else {
                    $this->buildCreateForm($form, $options);
                }
            }
        );
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();
                if ($data instanceof DataInterface) {
                    $data->setUpdatedAt(new \DateTime());
                }
            }
        );
    }

    /**
     * @param FormInterface $form
     * @param array         $options
     *
     * @throws \Exception
     */
    public function buildCreateForm(FormInterface $form, array $options)
    {
        $form->add('family', FamilySelectorType::class);
    }

    /**
     * For additional fields in data form that are not linked to EAV model
     *
     * @param FormInterface   $form
     * @param FamilyInterface $family
     * @param DataInterface   $data
     * @param array           $options
     */
    public function buildDataForm(
        FormInterface $form,
        FamilyInterface $family,
        DataInterface $data = null,
        array $options = []
    ) {
    }

    /**
     * @param FormInterface   $form
     * @param FamilyInterface $family
     * @param DataInterface   $data
     * @param array           $options
     *
     * @throws \Exception
     */
    public function buildValuesForm(
        FormInterface $form,
        FamilyInterface $family,
        DataInterface $data = null,
        array $options = []
    ) {
        foreach ($family->getAttributes() as $attribute) {
            $this->addAttribute($form, $attribute, $data, $options);
        }
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @throws AccessException
     * @throws UndefinedOptionsException
     * @throws MissingFamilyException
     * @throws \UnexpectedValueException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'family' => null,
                'data_class' => $this->dataClass,
            ]
        );
        $resolver->setNormalizer(
            'family',
            function (Options $options, $value) {
                if ($value === null) {
                    return null;
                }
                if ($value instanceof FamilyInterface) {
                    return $value;
                }

                return $this->familyRegistry->getFamily($value);
            }
        );
        $resolver->setNormalizer(
            'empty_data',
            function (Options $options, $value) {
                if ($options['family'] instanceof FamilyInterface) {
                    return $options['family']->createData();
                }

                return $value;
            }
        );
        $resolver->setNormalizer(
            'data_class',
            function (Options $options, $value) {
                if ($options['family'] instanceof FamilyInterface) {
                    return $options['family']->getDataClass();
                }

                return $value;
            }
        );
        $resolver->setNormalizer(
            'data',
            function (Options $options, $value) {
                if (null === $value) {
                    return null;
                }
                if (!$value instanceof DataInterface) {
                    throw new \UnexpectedValueException("The 'data' option should be a DataInterface");
                }
                if ($options['family'] instanceof FamilyInterface) {
                    $dataClass = $options['family']->getDataClass();
                    if (!is_a($value, $dataClass)) {
                        throw new \UnexpectedValueException("The 'data' option should be a {$dataClass}");
                    }
                }

                return $value;
            }
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'sidus_data';
    }

    /**
     * @param FormInterface      $form
     * @param AttributeInterface $attribute
     * @param DataInterface|null $data
     * @param array              $options
     *
     * @throws \Exception
     */
    protected function addAttribute(
        FormInterface $form,
        AttributeInterface $attribute,
        DataInterface $data = null,
        array $options = []
    ) {
        if ($attribute->getOption('hidden')) {
            return;
        }
        // The 'multiple' option triggers the usage of the Collection form type
        if ($attribute->isMultiple()) {
            // This means that a specific attribute can be a collection of data but might NOT be "multiple" in a sense
            // that it will not be edited as a "collection" form type.
            // Be wary of the vocabulary here
            $this->addMultipleAttribute($form, $attribute, $data, $options);
        } else {
            $this->addSingleAttribute($form, $attribute, $data, $options);
        }
    }

    /**
     * @param FormInterface      $form
     * @param AttributeInterface $attribute
     * @param DataInterface      $data
     * @param array              $options
     *
     * @throws \Exception
     */
    protected function addSingleAttribute(
        FormInterface $form,
        AttributeInterface $attribute,
        DataInterface $data = null,
        array $options = []
    ) {
        $formOptions = [];
        if (array_key_exists('form_options', $options)) {
            $formOptions = $options['form_options'];
        }

        $formOptions = array_merge(['label' => ucfirst($attribute)], $formOptions, $attribute->getFormOptions($data));
        unset($formOptions['collection_options']); // Ignoring collection_options if set

        $form->add($attribute->getCode(), $attribute->getFormType(), $formOptions);
    }

    /**
     * @param FormInterface      $form
     * @param AttributeInterface $attribute
     * @param DataInterface      $data
     * @param array              $options
     *
     * @throws \Exception
     */
    protected function addMultipleAttribute(
        FormInterface $form,
        AttributeInterface $attribute,
        DataInterface $data = null,
        array $options = []
    ) {
        $formOptions = [];
        if (array_key_exists('form_options', $options)) {
            $formOptions = $options['form_options'];
        }

        $formOptions = array_merge($formOptions, $attribute->getFormOptions($data));

        $formOptions['label'] = false; // Removing label
        $collectionOptions = [
            'label' => ucfirst($attribute),
            'entry_type' => $attribute->getFormType(),
            'entry_options' => $formOptions,
            'allow_add' => true,
            'allow_delete' => true,
            'required' => $attribute->isRequired(),
            'sortable' => false,
            'prototype_name' => '__'.$attribute->getCode().'__',
        ];
        if (!empty($formOptions['collection_options'])) {
            $collectionOptions = array_merge($collectionOptions, $formOptions['collection_options']);
        }
        unset($collectionOptions['entry_options']['collection_options']);
        $form->add($attribute->getCode(), $this->collectionType, $collectionOptions);
    }
}
