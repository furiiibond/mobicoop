import React from 'react';
import PropTypes from 'prop-types';

import { Grid } from '@material-ui/core';
import DayChip from './DayChip';

const formatDate = (d) => new Date(d).toLocaleDateString();
const formatDateTime = (d) => new Date(d).toLocaleString();

const SolidarySchedule = ({
  frequency,
  outwardDatetime,
  outwardDeadlineDatetime,
  returnDatetime,
  returnDeadlineDatetime,
  monCheck,
  tueCheck,
  wedCheck,
  thuCheck,
  friCheck,
  satCheck,
  sunCheck,
  marginDuration,
}) => {
  if (frequency === 2) {
    // Regular
    return (
      <Grid container direction="column" spacing={2}>
        <Grid container direction="row" justify="space-between" alignItems="center" spacing={2}>
          <Grid item>
            <b>Début:</b>
            <br />
            {formatDate(outwardDatetime)}
          </Grid>
          <Grid item>
            <b>Fin:</b>
            <br />
            {formatDate(returnDatetime)}
          </Grid>
        </Grid>
        <Grid item>
          {[
            { label: 'L', condition: monCheck },
            { label: 'M', condition: tueCheck },
            { label: 'Me', condition: wedCheck },
            { label: 'J', condition: thuCheck },
            { label: 'V', condition: friCheck },
            { label: 'S', condition: satCheck },
            { label: 'D', condition: sunCheck },
          ].map(({ label, condition }) => (
            <DayChip key={label} label={label} condition={condition} />
          ))}
        </Grid>
      </Grid>
    );
  }
  return (
    <Grid container spacing={1}>
      <Grid item xs={4}>
        Aller :
      </Grid>
      <Grid item xs={8}>
        {outwardDeadlineDatetime
          ? `entre le ${formatDateTime(outwardDatetime)} et le ${formatDate(
              outwardDeadlineDatetime
            )}`
          : formatDateTime(outwardDatetime)}
      </Grid>
      {returnDatetime && (
        <>
          <Grid item xs={4}>
            Retour :
          </Grid>
          <Grid item xs={8}>
            {returnDeadlineDatetime
              ? `entre le ${formatDateTime(returnDatetime)} et le ${formatDate(
                  returnDeadlineDatetime
                )}`
              : formatDateTime(returnDatetime)}
          </Grid>
        </>
      )}
      {marginDuration && (
        <>
          <Grid item xs={4}>
            Marge :
          </Grid>
          <Grid item xs={8}>
            {Math.round(marginDuration / 3600)} heures
          </Grid>
        </>
      )}
    </Grid>
  );
};

SolidarySchedule.propTypes = {
  frequency: PropTypes.number.isRequired,
  marginDuration: PropTypes.number.isRequired,
  outwardDatetime: PropTypes.string.isRequired,
  outwardDeadlineDatetime: PropTypes.string,
  returnDatetime: PropTypes.string,
  returnDeadlineDatetime: PropTypes.string,
  monCheck: PropTypes.bool,
  tueCheck: PropTypes.bool,
  wedCheck: PropTypes.bool,
  thuCheck: PropTypes.bool,
  friCheck: PropTypes.bool,
  satCheck: PropTypes.bool,
  sunCheck: PropTypes.bool,
};

SolidarySchedule.defaultProps = {
  monCheck: false,
  tueCheck: false,
  wedCheck: false,
  thuCheck: false,
  friCheck: false,
  satCheck: false,
  sunCheck: false,
  outwardDeadlineDatetime: null,
  returnDatetime: null,
  returnDeadlineDatetime: null,
};

export default SolidarySchedule;