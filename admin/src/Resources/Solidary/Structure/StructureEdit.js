import React from 'react';
import { useForm } from 'react-final-form';

import {
  Edit,
  required,
  TextInput,
  TabbedForm,
  SaveButton,
  FormTab,
  SimpleFormIterator,
  SelectInput,
  Toolbar,
  BooleanInput,
  ArrayInput,
  FormDataConsumer,
} from 'react-admin';

import { StructureTimeSlotsInput } from './Input/StructureTimeSlotsInput';
import { proofTypeLabels } from '../../../constants/proofType';

// Because of <AvailabilityRangeDialogButton /> blur
// The pristine is set to true on modal close, so we force it here
// @TODO: Understand why the pristine status disappear
const EnabledSaveButton = (props) => <SaveButton {...props} pristine={false} />;
const StructureEditToolbar = (props) => (
  <Toolbar {...props}>
    <EnabledSaveButton />
  </Toolbar>
);

const SwitchableFieldsSelectInput = ({ choices, source }) => {
  const form = useForm();
  const handleChange = (e) => {
    Object.keys(proofTypeLabels).forEach((key) => {
      console.log(`${source}.${key}`);
      form.change(`${source}.${key}`, key === e.target.value);
    });
  };

  return (
    <SelectInput
      source={`switchable_${source}`}
      onChange={handleChange}
      choices={Object.keys(proofTypeLabels).map((id) => ({
        id,
        name: proofTypeLabels[id],
      }))}
    />
  );
};

const PositionInput = (props) => {
  const rx = new RegExp(/structureProofs\[(.*)\].position/g);
  const match = rx.exec(props.id);
  return <TextInput {...props} initialValue={parseInt(match[1], 10)} style={{ display: 'none' }} />;
};

export const StructureEdit = (props) => (
  <Edit {...props} title="Structures accompagnantes > éditer">
    <TabbedForm toolbar={<StructureEditToolbar />}>
      <FormTab label="summary">
        <TextInput source="name" label="Nom" validate={required()} />
        <StructureTimeSlotsInput />
      </FormTab>
      <FormTab label="Object du déplacement">
        <ArrayInput label="" source="subjects">
          <SimpleFormIterator>
            <TextInput source="label" label="Titre" />
          </SimpleFormIterator>
        </ArrayInput>
      </FormTab>
      <FormTab label="Critères éligibilité demandeurs">
        <ArrayInput label="" source="structureProofs">
          <SimpleFormIterator>
            <FormDataConsumer>
              {({ formData }) => (
                <TextInput source="structure_id" initialValue={formData.originId} />
              )}
            </FormDataConsumer>
            <PositionInput source="position" />
            <TextInput source="label" label="Titre" />
            <SwitchableFieldsSelectInput />
            <BooleanInput label="Obligatoire" source="mandatory" />
            <SelectInput
              source="type"
              choices={[
                { id: 1, name: 'Solidary Requester' },
                { id: 2, name: 'Volunteer' },
              ]}
            />
          </SimpleFormIterator>
        </ArrayInput>
      </FormTab>
      <FormTab label="Je suis prêt à">
        <ArrayInput label="" source="needs">
          <SimpleFormIterator>
            <TextInput source="label" label="Titre" />
          </SimpleFormIterator>
        </ArrayInput>
      </FormTab>
    </TabbedForm>
  </Edit>
);