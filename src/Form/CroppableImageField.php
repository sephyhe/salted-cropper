<?php

namespace SaltedHerring\Salted\Cropper\Fields;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FormAction;
use SaltedHerring\Salted\Cropper\SaltedCroppableImage;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Control\Controller;

/**
 * CroppableImageField
 *
 * @package silverstripe-linkable
 * @license BSD License http://www.silverstripe.org/bsd-license
 * @author <shea@silverstripe.com.au>
 **/

class CroppableImageField extends FormField
{
    /**
     * @var Boolean
     **/
    protected $isFrontend = false;
    protected $folderName = false;

    /**
     * @var SaltedCroppableImage
     **/
    protected $linkObject;

    /**
     * List the allowed included link types.  If null all are allowed.
     *
     * @var array
     **/
    protected $allowed_types = null;

    private static $allowed_actions = [
        'CroppableImageForm',
        'CroppableImageFormHTML',
        'doSaveCroppableImage',
        'doRemoveCroppableImage'
    ];

    public function setCropperRatio($ratio)
    {
        $this->Ratio = $ratio;
        return $this;
    }

    public function Field($properties = [])
    {
        Requirements::css('salted-herring/salted-cropper: client/css/salted-croppable.css');
        Requirements::css('salted-herring/salted-cropper: client/css/salted-cropper.css');
        Requirements::javascript('salted-herring/salted-cropper: client/js/salted-croppable-field.js');

        return parent::Field();
    }

    /**
     * The CroppableImageForm for the dialog window
     *
     * @return Form
     **/
    public function CroppableImageForm()
    {
        $action = FormAction::create('doSaveCroppableImage', _t('CroppableImageable.SAVE', 'Save'))
            ->setUseButtonTag(true)
            ->addExtraClass('btn-primary font-icon-save');

        if (!$this->isFrontend) {
            $action->addExtraClass('btn-primary font-icon-save')->setAttribute('data-icon', 'accept');
        }

        $image = null;
        if ($croppableImageID = (int) $this->getRequest()->getVar('SaltedCroppableImageID')) {
            $image = SaltedCroppableImage::get()->byID($croppableImageID);
        }
        $image = $image ?: SaltedCroppableImage::singleton();

        $fields = $image->getCMSFields();

        $title = $image->exists()
            ? _t('CroppableImageable.EDITIMAGE', 'Edit Image')
            : _t('CroppableImageable.ADDIMAGE', 'Add Image');
        $fields->insertBefore('Original', HeaderField::create('CroppableImageHeader', $title));
        $actions = FieldList::create($action);
        $form = Form::create($this, 'CroppableImageForm', $fields, $actions);

        if ($image->exists()) {
            $form->loadDataFrom($image);
        }

        if (!empty($this->folderName)) {
            $originalField = $fields->fieldByName('Root.Main.Original');
            if ($originalField) {
                $originalField->setFolderName($this->folderName);
            }
        }

        $fields->push(HiddenField::create('CropperRatio')->setValue($this->Ratio));
        $fields->push(HiddenField::create('ContainerX')->setValue($image->ContainerX));
        $fields->push(HiddenField::create('ContainerY')->setValue($image->ContainerY));
        $fields->push(HiddenField::create('ContainerWidth')->setValue($image->ContainerWidth));
        $fields->push(HiddenField::create('ContainerHeight')->setValue($image->ContainerHeight));
        $fields->push(HiddenField::create('CropperX')->setValue($image->CropperX));
        $fields->push(HiddenField::create('CropperY')->setValue($image->CropperY));
        $fields->push(HiddenField::create('CropperWidth')->setValue($image->CropperWidth));
        $fields->push(HiddenField::create('CropperHeight')->setValue($image->CropperHeight));

        $this->extend('updateLinkForm', $form);

        return $form;
    }


    /**
     * Either updates the current link or creates a new one
     * Returns field template to update the interface
     * @return string
     **/
    public function doSaveCroppableImage($data, $form = null, $request = null, $handler = null)
    {
        if (!$form instanceof Form) {
            $form = $this->CroppableImageForm();
        }

        $link = $this->getCroppableImageObject() ?: SaltedCroppableImage::create();
        $form->saveInto($link);
        try {
            $link->write();
        } catch (ValidationException $e) {
            $form->sessionMessage($e->getMessage(), 'bad');
            return $form->forTemplate();
        }

        $this->setValue($link->ID);
        return $this->FieldHolder();
    }


    /**
     * Delete link action
     *
     * @return string
     **/
    public function doRemoveCroppableImage($request = null)
    {
        if ($image = SaltedCroppableImage::get()->byID($this->value)) {
            $image->delete();
        }

        $this->setValue('');
        return $this->FieldHolder();
    }


    /**
     * Returns the current link object
     *
     * @return SaltedCroppableImage|null
     **/
    public function getCroppableImageObject()
    {
        $request = $this->getRequest();
        $requestID = $request->requestVar('SaltedCroppableImageID');
        if ($requestID === null) {
            $requestID = $request->requestVar('CroppableImageID');
        }

        if ($requestID == '0' && !$this->Value()) {
            return null;
        }

        if (!$this->linkObject) {
            $id = $this->Value() ?: $requestID;
            if ((int) $id) {
                $this->linkObject = SaltedCroppableImage::get()->byID($id);
            }
        }
        return $this->linkObject;
    }


    /**
     * Returns the HTML of the CroppableImageForm for the dialog
     *
     * @return string
     **/
    public function CroppableImageFormHTML()
    {
        return $this->CroppableImageForm()->forTemplate();
    }


    public function getIsFrontend()
    {
        return $this->isFrontend;
    }


    public function setIsFrontend($bool)
    {
        $this->isFrontend = $bool;
        return $this;
    }

    public function setAllowedTypes($types = array())
    {
        $this->allowed_types = $types;
        return $this;
    }

    public function getAllowedTypes()
    {
        return $this->allowed_types;
    }

    public function getEditLink()
    {
        return Controller::join_links(
            Controller::curr()->Link(),
            'EditForm/field/' . $this->name . '/CroppableImageFormHTML?SaltedCroppableImageID=' . $this->value
        );
    }

    public function getCroppedImage()
    {
        if ($id = $this->value) {
            return SaltedCroppableImage::get()->byID($id);
        }

        return null;
    }

    public function timestamp()
    {
        return time();
    }

    public function setFolderName($folderName)
    {
        $this->folderName = $folderName;
        return $this;
    }
}
