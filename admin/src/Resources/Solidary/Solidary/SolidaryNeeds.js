import React from 'react'
import { CheckboxGroupInput} from 'react-admin'
import {LinearProgress, } from '@material-ui/core'

const SolidaryNeeds = () => {
    // @TODO : uncomment this as soon as structures/needs API will be OK
    // const {data, error, loading, loaded}  = useGetList("structures/needs", { page: 1 , perPage: 10 }, { field: 'id', order: 'ASC' })
    // const needs = Object.values(data)
    
    // Fake data
    const needs=[
        { id: 1, name: "J'ai besoin d'être accompagné jusqu'à ma porte" },
        { id: 2, name: "J'invite à prendre un café"},
        { id: 3, name: "J'ai besoin qu'on monte mes courses" },
    ]

    if (!needs.length) {
        return <LinearProgress />
    } 
    
    return (
        <CheckboxGroupInput source="needs" label="" choices={needs} />
    )

}

export default SolidaryNeeds

