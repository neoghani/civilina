<?php

namespace Tests\Unit;

use App\Services\Engineering\NormativeLibrarian\NormativeMetadataParser;
use PHPUnit\Framework\TestCase;

class NormativeMetadataParserTest extends TestCase
{
    public function test_parses_nf_dtu_20_1_cover(): void
    {
        $parser = new NormativeMetadataParser();

        $text = <<<'TXT'
FA155157
ISSN 0335-3931
norme française
NF DTU 20.1 P1-2
Octobre 2008
Indice de classement : P 10-202-1-2
ICS : 91.060.10 ; 91.080.30

Travaux de bâtiment

Ouvrages en maçonnerie
de petits éléments — Parois et murs

Partie 1-2 : Critères généraux de choix des matériaux

Norme française homologuée
par décision du Directeur Général d'AFNOR
TXT;

        $data = $parser->parse('DTU 20-1.pdf', $text);

        $this->assertSame('NF DTU 20.1 P1-2', $data['reference_code']);
        $this->assertSame('AFNOR', $data['issuer']);
        $this->assertSame('Travaux de bâtiment', $data['category_label']);
        $this->assertSame('Ouvrages en maçonnerie de petits éléments — Parois et murs', $data['title']);
        $this->assertSame('Partie 1-2 : Critères généraux de choix des matériaux', $data['subtitle']);
        $this->assertSame('Octobre', $data['publication_month']);
        $this->assertSame(2008, $data['publication_year']);
        $this->assertSame('P 10-202-1-2', $data['classification_index']);
        $this->assertSame(['91.060.10','91.080.30'], $data['ics_codes']);
        $this->assertSame('Bâtiment', $data['discipline']);
        $this->assertSame('CES', $data['domain']);
        $this->assertSame('Maçonnerie', $data['subdomain']);
        $this->assertSame('AUTO_CLASSIFIED', $data['classification_status']);
    }
}
