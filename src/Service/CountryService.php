<?php

namespace App\Service;

class CountryService
{
    public function getCountries(): array
    {
        return [
            'BJ' => 'Bénin',
            'BF' => 'Burkina Faso',
            'CV' => 'Cap-Vert',
            'CI' => 'Côte d\'Ivoire',
            'GM' => 'Gambie',
            'GH' => 'Ghana',
            'GN' => 'Guinée',
            'GW' => 'Guinée-Bissau',
            'LR' => 'Libéria',
            'ML' => 'Mali',
            'MR' => 'Mauritanie',
            'NE' => 'Niger',
            'NG' => 'Nigéria',
            'SN' => 'Sénégal',
            'SL' => 'Sierra Leone',
            'TG' => 'Togo',
            'CM' => 'Cameroun',
            'CF' => 'République centrafricaine',
            'TD' => 'Tchad',
            'CG' => 'République du Congo',
            'CD' => 'République démocratique du Congo',
            'GQ' => 'Guinée équatoriale',
            'GA' => 'Gabon',
            'ST' => 'Sao Tomé-et-Principe',
            'DZ' => 'Algérie',
            'EG' => 'Égypte',
            'LY' => 'Libye',
            'MA' => 'Maroc',
            'SD' => 'Soudan',
            'TN' => 'Tunisie',
            'FR' => 'France',
            'BE' => 'Belgique',
            'CH' => 'Suisse',
            'CA' => 'Canada',
            'US' => 'États-Unis',
        ];
    }

    public function getPhoneCodes(): array
    {
        return [
            '+229' => 'Bénin',
            '+226' => 'Burkina Faso',
            '+225' => 'Côte d\'Ivoire',
            '+220' => 'Gambie',
            '+233' => 'Ghana',
            '+224' => 'Guinée',
            '+245' => 'Guinée-Bissau',
            '+231' => 'Libéria',
            '+223' => 'Mali',
            '+222' => 'Mauritanie',
            '+227' => 'Niger',
            '+234' => 'Nigéria',
            '+221' => 'Sénégal',
            '+232' => 'Sierra Leone',
            '+228' => 'Togo',
            '+237' => 'Cameroun',
            '+235' => 'Tchad',
            '+242' => 'République du Congo',
            '+243' => 'République démocratique du Congo',
            '+240' => 'Guinée équatoriale',
            '+241' => 'Gabon',
            '+239' => 'Sao Tomé-et-Principe',
            '+213' => 'Algérie',
            '+20'  => 'Égypte',
            '+218' => 'Libye',
            '+212' => 'Maroc',
            '+249' => 'Soudan',
            '+216' => 'Tunisie',
            '+33'  => 'France',
            '+32'  => 'Belgique',
            '+41'  => 'Suisse',
            '+1'   => 'Canada/États-Unis',
        ];
    }

    public function getNationalities(): array
    {
        return [
            'BJ' => 'Béninois(e)',
            'BF' => 'Burkinabè',
            'CV' => 'Cap-verdien(ne)',
            'CI' => 'Ivoirien(ne)',
            'GM' => 'Gambien(ne)',
            'GH' => 'Ghanéen(ne)',
            'GN' => 'Guinéen(ne)',
            'GW' => 'Bissau-Guinéen(ne)',
            'LR' => 'Libérien(ne)',
            'ML' => 'Malien(ne)',
            'MR' => 'Mauritanien(ne)',
            'NE' => 'Nigérien(ne)',
            'NG' => 'Nigérian(e)',
            'SN' => 'Sénégalais(e)',
            'SL' => 'Sierra-Léonais(e)',
            'TG' => 'Togolais(e)',
            'CM' => 'Camerounais(e)',
            'CF' => 'Centrafricain(e)',
            'TD' => 'Tchadien(ne)',
            'CG' => 'Congolais(e) (Congo)',
            'CD' => 'Congolais(e) (RDC)',
            'GQ' => 'Équato-Guinéen(ne)',
            'GA' => 'Gabonais(e)',
            'ST' => 'Santoméen(ne)',
            'DZ' => 'Algérien(ne)',
            'EG' => 'Égyptien(ne)',
            'LY' => 'Libyen(ne)',
            'MA' => 'Marocain(e)',
            'SD' => 'Soudanais(e)',
            'TN' => 'Tunisien(ne)',
            'FR' => 'Français(e)',
            'BE' => 'Belge',
            'CH' => 'Suisse',
            'CA' => 'Canadien(ne)',
            'US' => 'Américain(e)'
        ];
    }
}
