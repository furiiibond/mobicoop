import React, { useState } from 'react';
import BlockIcon from '@material-ui/icons/Block';
import { TableCell, TableRow, Checkbox } from '@material-ui/core';

import {
  List,
  Datagrid,
  TextInput,
  SelectInput,
  ReferenceInput,
  BooleanInput,
  TextField,
  EmailField,
  DateField,
  EditButton,
  BooleanField,
  DatagridBody,
  Filter,
  Button,
  useTranslate,
} from 'react-admin';

import EmailComposeButton from '../../components/email/EmailComposeButton';
import ResetButton from '../../components/button/ResetButton';
import isAuthorized from '../../auth/permissions';

const UserList = (props) => {
  const translate = useTranslate();
  const [count, setCount] = useState(0);

  const BooleanStatusField = ({ record = {}, source }) => {
    const theRecord = { ...record };
    theRecord[source + 'Num'] = !!parseInt(record.status === 1 ? 1 : 0);
    return (
      <BooleanField
        record={theRecord}
        source={source + 'Num'}
        valueLabelTrue="custom.label.user.accountEnabled"
        valueLabelFalse="custom.label.user.accountDisabled"
      />
    );
  };

  const checkValue = ({ selected, record }) => {
    if (record.newsSubscription === false) setCount(selected === false ? count + 1 : count - 1);
  };
  const MyDatagridRow = ({ record, resource, id, onToggleItem, children, selected, basePath }) => {
    if (selected && record.newsSubscription === false) setCount(1);
    return (
      <TableRow key={id} hover={true}>
        {/* first column: selection checkbox */}
        <TableCell padding="none">
          <Checkbox
            checked={selected}
            onClick={() => {
              onToggleItem(id);
              checkValue({ selected, record });
            }}
          />
        </TableCell>
        {/* data columns based on children */}
        {React.Children.map(children, (field) => (
          <TableCell key={`${id}-${field.props.source}`}>
            {React.cloneElement(field, {
              record,
              basePath,
              resource,
            })}
          </TableCell>
        ))}
      </TableRow>
    );
  };
  const MyDatagridBody = (props) => <DatagridBody {...props} row={<MyDatagridRow />} />;
  const MyDatagridUser = (props) => <Datagrid {...props} body={<MyDatagridBody />} />;

  const UserBulkActionButtons = (props) => {
    return (
      <>
        {isAuthorized('mass_create') && count === 0 ? (
          <EmailComposeButton label="Email" {...props} />
        ) : (
          <Button
            label={translate('custom.email.texte.blockUnsubscribe')}
            startIcon={<BlockIcon />}
          />
        )}
        <ResetButton label="Reset email" {...props} />
        {/* default bulk delete action */}
        {/* <BulkDeleteButton {...props} /> */}
      </>
    );
  };
  const UserFilter = (props) => (
    <Filter {...props}>
      <TextInput source="givenName" label={translate('custom.label.user.givenName')} />
      <TextInput source="familyName" label={translate('custom.label.user.familyName')} alwaysOn />
      <TextInput source="email" label={translate('custom.label.user.email')} alwaysOn />
      {/* <BooleanInput source="solidary" label={translate('custom.label.user.solidary')} allowEmpty={false} defaultValue={true} /> */}
      <BooleanInput
        source="solidaryCandidate "
        label={translate('custom.label.user.candidate')}
        allowEmpty={false}
        defaultValue={true}
      />
      <BooleanInput
        source="solidaryUser.volunteer"
        label={translate('custom.label.user.volunteer')}
        allowEmpty={false}
        defaultValue={true}
      />
      <ReferenceInput
        source="homeAddressODTerritory"
        label={translate('custom.label.user.territory')}
        reference="territories"
        allowEmpty={false}
        resettable
      >
        <SelectInput optionText="name" optionValue="id" />
      </ReferenceInput>
    </Filter>
  );

  return (
    <List
      {...props}
      title="Utilisateurs > liste"
      perPage={10}
      filters={<UserFilter />}
      sort={{ field: 'id', order: 'ASC' }}
      bulkActionButtons={<UserBulkActionButtons />}
      //exporter={isAuthorized("right_user_assign") ? defaultExporter : false}
      exporter={false}
      hasCreate={isAuthorized('user_create')}
    >
      <MyDatagridUser rowClick="show">
        <TextField source="originId" label={translate('custom.label.user.id')} sortBy="id" />
        <TextField source="givenName" label={translate('custom.label.user.givenName')} />
        <TextField source="familyName" label={translate('custom.label.user.familyName')} />
        <EmailField source="email" label={translate('custom.label.user.email')} />
        <BooleanField
          source="newsSubscription"
          label={translate('custom.label.user.accepteEmail')}
        />
        <BooleanStatusField source="status" label={translate('custom.label.user.accountStatus')} />
        <DateField source="createdDate" label={translate('custom.label.user.createdDate')} />
        {isAuthorized('user_update') && <EditButton />}
      </MyDatagridUser>
    </List>
  );
};

export default UserList;