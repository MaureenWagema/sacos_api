<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;

class premCalController extends Controller
{
    //lets calculate the rider premium
    public function riderPremium(Request $request){
        try{
            $res = array();
            
            $sum_assured = $request->input('sum_assured');
            $rider_code = $request->input('rider_code');
            $age = $request->input('age');
            $plan_code = $request->input('plan_code');
            $term = $request->input('term');
            $pay_mode_id = $request->input('pay_mode_id');

            if ($sum_assured > 0) {
                $dblPrmRate = 0;
                $ClientEntryAgeVAR = $age;
                $plan_codeVAR = $plan_code;
                $term_of_policyVAR = $term;
                $AgeRangeQueryPartVAR = null;
                $TermRangeQueryPartVAR = null;
                $dblRateBasis = 0;
                $w_temp = 0;

                $plan_rider_config = $this->smartlife_db->table('plan_rider_config')
                    ->where('rider_code', $rider_code)
                    ->where('plan_code', $plan_code)
                    ->first();

                if (!$plan_rider_config) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Rider information not found'
                    ]);
                }

                $UseAgeRange = $plan_rider_config->UseAgeRange ?? false;
                $UseTerm = $plan_rider_config->UseTerm ?? false;
                $UseTermRange = $plan_rider_config->UseTermRange ?? false;
                $use_flat_rate = $plan_rider_config->use_flat_rate ?? false;
                $rate = $plan_rider_config->rate ?? 0;
                $rate_basis = $plan_rider_config->rate_basis;
                $TaxRate = $plan_rider_config->TaxRate ?? 0;
                $TaxRateIsPerc = $plan_rider_config->TaxRateIsPerc ?? false;
                $policyFee = $plan_rider_config->policyFee ?? 0;
                $policyFeeRateIsPerc = $plan_rider_config->policyFeeRateIsPerc ?? false;

                //pick configs from rider_info
                $rider_info = $this->smartlife_db->table('rider_info')
                    ->where('rider_code', $rider_code)
                    ->first();

                $adb = $rider_info->adb ?? false;
                $acdb = $rider_info->acdb ?? false;
                $ptd = $rider_info->ptd ?? false;
                $cic = $rider_info->cic ?? false;
                $wp = $rider_info->wp ?? false;
                $FIBDeath = $rider_info->FIBDeath ?? false;
                $FIBDisability = $rider_info->FIBDisability ?? false;

                if ($UseAgeRange == true) {
                    $AgeRangeQueryPartVAR = "$ClientEntryAgeVAR BETWEEN t.age AND t.age2";
                } else {
                    $AgeRangeQueryPartVAR = "t.age = $ClientEntryAgeVAR";
                }

                if ($UseTerm == true) {
                    if ($UseTermRange == true) {
                        $TermRangeQueryPartVAR = " AND $term_of_policyVAR BETWEEN t.term_from AND t.term_to";
                    } else {
                        $TermRangeQueryPartVAR = " AND t.term_from = $term_of_policyVAR";
                    }
                } else {
                    $TermRangeQueryPartVAR = "";
                }

                if ($use_flat_rate == false) {
                    $sql = "SELECT * FROM rider_premuim_rates t 
                            INNER JOIN rider_info r ON r.rider_code = t.rider_code 
                            WHERE t.rider_code = '$rider_code' 
                            AND t.PlanCode = $plan_codeVAR 
                            AND $AgeRangeQueryPartVAR 
                            $TermRangeQueryPartVAR";

                    $_rsRiderRate = DbHelper::getTableRawData($sql);

                    if ($_rsRiderRate && count($_rsRiderRate) > 0) {
                        $recRow = $_rsRiderRate[0];
                        $dblPrmRate = (float)$recRow->normal_rate;
                        
                        if ($use_flat_rate == false) {
                            if (isset($recRow->rate_basis) && $recRow->rate_basis != null) {
                                $dblRateBasis = (float)$recRow->rate_basis;
                            }
                        }
                    }
                } else {
                    $dblPrmRate = $rate;
                }

                if ($dblPrmRate == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Rate Not Found!"
                    ]);
                }

                if ($use_flat_rate == true) {
                    $dblRateBasis = $rate_basis;
                }

                if ($dblRateBasis == 0) {
                    //$dblRateBasis = 1;
                    return response()->json([
                        'success' => false,
                        'message' => 'Rate Basis Not Found! Please Check Rider Configuration'
                    ]);
                }

                $TaxVAR = 0;
                if ($TaxRate > 0) {
                    if ($TaxRateIsPerc == false) {
                        $TaxVAR = $TaxRate;
                    } else {
                        $TaxVAR = ($TaxRate / 100);
                    }
                }

                $loadingFactorVAR = 1;
                if ($pay_mode_id) {
                    $paymode_loading = DbHelper::getColumnValue('paymentmodeinfo', 'id', $pay_mode_id, 'loadingfactor');
                    if ($paymode_loading && $paymode_loading > 0) {
                        $loadingFactorVAR = $paymode_loading;
                    }
                }

                $PolicyFeeVAR = 0;
                if ($policyFee > 0) {
                    if ($policyFeeRateIsPerc == false) {
                        $PolicyFeeVAR = $policyFee;
                    } else {
                        $PolicyFeeVAR = ($policyFee / 100);
                    }
                }

                $X = 0;
                $Y = 0;
                $Z = 0;

                if ($adb == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * $sum_assured;
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($acdb == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * $sum_assured;
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($ptd == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * $sum_assured;
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($cic == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * $sum_assured;
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($wp == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * $sum_assured;
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($FIBDeath == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * ((3 / 100) * $sum_assured);
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else if ($FIBDisability == true) {
                    $X = ($dblPrmRate / $dblRateBasis) * ((3 / 100) * $sum_assured);
                    $Y = $X + $PolicyFeeVAR;
                    $Z = ($Y * $loadingFactorVAR) + $TaxVAR;
                    $w_temp = $Z;
                } else {
                    $w_temp = 0;
                }

                if ($w_temp > 0) {
                    $premium = round($w_temp, 2);
                } else {
                    $premium = 0;
                }

                $res = array(
                    'success' => true,
                    'premium' => number_format((float)$premium, 2, '.', ''),
                    'rate' => $dblPrmRate,
                    'rate_basis' => $dblRateBasis,
                    'loading_factor' => $loadingFactorVAR,
                    'policy_fee' => $PolicyFeeVAR,
                    'tax' => $TaxVAR,
                    'message' => 'Rider Premium Calculated Successfully!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Sum assured must be greater than 0'
                );
            }

        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }
    //lets create function FuneralPolicies
    public function FuneralPolicies(Request $request){
        try{
            $res = array();
            //we pass: plan_code, age & sum_assured
            $plan_code = $request->input('plan_code');
            $age = $request->input('age');
            $sum_assured = $request->input('sum_assured');

            //we using table: funeralratesinfo
            //for age we search where greater or equal than Min_age & less than Max_age
            //we match plan_code & SumAssured
            $qry = $this->smartlife_db->table('funeralratesinfo as p')
            ->select('*')
            ->where('p.Min_age', '<=', $age)
            ->where('p.Max_age', '>=', $age)
            ->where(array('p.plan_code' => $plan_code, 'p.SumAssured' => $sum_assured));
            $results = $qry->first();
            if($results){
                $premium = $results->Rate;
                $res = array(
                    'success' => true,
                    'premium' => $premium,
                    'message' => 'Premium Calculated Successfully!!'
                );
            }else{
                $res = array(
                    'success' => false,
                    'message' => 'Premium Not Found!!'
                );
            }
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    public function ComputePremiumForProducts(Request $request){
        try{
            $res = array();
            
            $plan_code = $request->input('plan_code');
            $age = $request->input('age');
            $term = $request->input('term');
            $sa = $request->input('sa');
            $pay_mode_id = $request->input('pay_mode_id');
            $UseCustomPremRate = $request->input('UseCustomPremRate') ?? false;
            $Prem_rate = $request->input('Prem_rate') ?? 0;
            $relationship_code = $request->input('relationship_code');
            $mortgage_option = $request->input('mortgage_option');
            $age2 = $request->input('age2');
            $DiscountRate = $request->input('DiscountRate') ?? 0;
            //$client_number = $request->input('client_number');
            
            $dblPrmRate = 0;
            $w_rate2 = 0;
            $w_rate3 = 0;
            $dblRateBasis = 0;
            
            if (!$plan_code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product Is Mandatory!'
                ]);
            }
            
            $plan_info = $this->smartlife_db->table('planinfo')
                ->where('plan_code', $plan_code)
                ->first();
                
            if (!$plan_info) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found!'
                ]);
            }
            
            $Funeral_cover = $plan_info->Funeral_cover ?? false;
            $UseFixedPremRate = $plan_info->UseFixedPremRate ?? false;
            $premium_table = $plan_info->premium_table ?? 0;
            $FixedRateBasis = $plan_info->FixedRateBasis ?? false;
            $rate_basis_plan = $plan_info->rate_basis ?? 0;
            $policyFee = $plan_info->policyFee ?? 0;
            $policyFeeRateIsPerc = $plan_info->policyFeeRateIsPerc ?? false;
            $TaxRate = $plan_info->TaxRate ?? 0;
            $TaxRateIsPerc = $plan_info->TaxRateIsPerc ?? false;
            $CategoryCode = $plan_info->CategoryCode ?? 0;
            $UseAgeRangeFuneralRates = $plan_info->UseAgeRangeFuneralRates ?? false;
            $FuneralRateTable = $plan_info->FuneralRateTable ?? 0;
            $ReinsuranceMinSA = $plan_info->ReinsuranceMinSA ?? 0;
            
            $category_info = $this->smartlife_db->table('plan_prop_category')
                ->where('prop_code', $CategoryCode)
                ->first();
                
            $OrdinaryLifeProduct = $category_info->OrdinaryLifeProduct ?? false;
            $FuneralProduct = $category_info->FuneralProduct ?? false;
            $MortgageProduct = $category_info->MortgageProduct ?? false;
            $CreditLifeProduct = $category_info->CreditLifeProduct ?? false;
            
            if ($UseCustomPremRate == false) {
                if ($Funeral_cover == false) {
                    if ($UseFixedPremRate == false) {
                        $sql = "SELECT * FROM premium_rate_setup 
                                WHERE plan_code = '$plan_code' 
                                AND age = $age 
                                AND table_code = $premium_table 
                                AND term = $term";
                        
                        $_dataSet = DbHelper::getTableRawData($sql);
                        
                        if ($_dataSet && count($_dataSet) > 0) {
                            $recRow = $_dataSet[0];
                            $dblPrmRate = (float)$recRow->rate;
                            
                            if ($FixedRateBasis == true) {
                                $dblRateBasis = $rate_basis_plan;
                            } else {
                                $dblRateBasis = (float)$recRow->rate_basis;
                            }
                            
                            if ($dblPrmRate == 0) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Rate Not Found! Please Check... For Policy Term: $term"
                                ]);
                            }
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'Rate Not Found! Please Check...'
                            ]);
                        }
                    } else {
                        if ($plan_info->Prem_rate <= 0) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Rate Not Found! Please Check Product Details'
                            ]);
                        }
                        $dblPrmRate = $plan_info->Prem_rate;
                    }
                } else {
                    $CategoryCodeRel = DbHelper::getColumnValue('RelationshipCategory', 'description', $relationship_code, 'id');
                    
                    if ($UseAgeRangeFuneralRates == true) {
                        $sql = "SELECT t1.* FROM funeralratesinfo t1 
                                WHERE t1.CategoryCode = $CategoryCodeRel 
                                AND t1.plan_code = $plan_code 
                                AND t1.SumAssured = $sa 
                                AND $age BETWEEN t1.min_age AND t1.max_age 
                                AND t1.tableCode = $FuneralRateTable";
                    } else {
                        $sql = "SELECT t1.* FROM funeralratesinfo t1 
                                WHERE t1.CategoryCode = $CategoryCodeRel 
                                AND t1.plan_code = $plan_code 
                                AND t1.SumAssured = $sa 
                                AND t1.min_age = $age 
                                AND t1.tableCode = $FuneralRateTable";
                    }
                    
                    $_dataSet = DbHelper::getTableRawData($sql);
                    
                    if ($_dataSet && count($_dataSet) > 0) {
                        $recRow = $_dataSet[0];
                        if (isset($recRow->rate) && $recRow->rate != null) {
                            $dblPrmRate = (float)$recRow->rate;
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => 'No Premium Rate Found.. Please Check Sumassured'
                            ]);
                        }
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'No Premium Rate Found.. Please Check Sumassured'
                        ]);
                    }
                }
            } else if ($UseCustomPremRate == true) {
                $dblPrmRate = $Prem_rate;
                $dblRateBasis = $rate_basis_plan;
                
                if ($Prem_rate == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Custom Premium Rate Has Not Been Specified! Please Check...'
                    ]);
                }
            }
            
            if ($dblRateBasis <= 0 && $Funeral_cover == false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rate Basis Is Mandatory! Check Product Details'
                ]);
            }
            
            $w_temp = 0;
            $w_temp2 = 0;
            $w_temp3 = 0;
            $PolicyFeeVAR = 0;
            $TaxVAR = 1;
            $loadingFactorVAR = 0;
            $basic_premVAR = 0;
            
            if ($policyFee > 0) {
                if ($policyFeeRateIsPerc == false) {
                    $PolicyFeeVAR = $policyFee;
                } else {
                    $PolicyFeeVAR = ($policyFee / 100);
                }
            }
            
            if ($TaxRate > 0) {
                if ($TaxRateIsPerc == false) {
                    $TaxVAR = $TaxRate;
                } else {
                    $TaxVAR = ($TaxRate / 100);
                }
            }
            
            if ($pay_mode_id) {
                $paymode_info = $this->smartlife_db->table('paymentmodeinfo')
                    ->where('id', $pay_mode_id)
                    ->first();
                    
                $loadingFactorVAR = $paymode_info->loadingfactor ?? 1;
                $coverperiod = $paymode_info->coverperiod ?? 1;
                
                if ($loadingFactorVAR <= 0) {
                    $loadingFactorVAR = 1;
                }
            } else {
                $loadingFactorVAR = 1;
                $coverperiod = 1;
            }
            
            $PTDRiderPrem = 0;
            $total_rider_prem = 0;
            
            if ($OrdinaryLifeProduct == true) {
                $w_temp = ((($dblPrmRate / $dblRateBasis) * $sa) + $PolicyFeeVAR) * $loadingFactorVAR * $TaxVAR;
            } else if ($FuneralProduct == true) {
                $w_temp = round($dblPrmRate * $coverperiod, 2);
            } else if ($MortgageProduct == true) {
                if ($mortgage_option == 'single') {
                    $w_temp2 = ($dblPrmRate / $dblRateBasis) * $sa;
                    
                    $plan_rider = $this->smartlife_db->table('plan_rider_config as prc')
                        ->join('rider_info as ri', 'ri.rider_code', '=', 'prc.rider_code')
                        ->where('prc.plan_code', $plan_code)
                        ->where('ri.ptd', true)
                        ->where('prc.IsAutomatic', true)
                        ->first();
                        
                    if ($plan_rider) {
                        $w_temp3 = ($plan_rider->rate / $plan_rider->rate_basis) * $sa * $term;
                        $PTDRiderPrem = $w_temp3;
                        $total_rider_prem = $w_temp3;
                    }
                    
                    $w_temp = ($w_temp2 + $w_temp3 + $PolicyFeeVAR) * $TaxVAR;
                } else if ($mortgage_option == 'joint') {
                    $sql = "SELECT * FROM premium_rate_setup 
                            WHERE plan_code = '$plan_code' 
                            AND age = $age2 
                            AND table_code = $premium_table 
                            AND term = $term";
                    
                    $_dataSet = DbHelper::getTableRawData($sql);
                    
                    if ($_dataSet && count($_dataSet) > 0) {
                        $recRow = $_dataSet[0];
                        $w_rate2 = (float)$recRow->rate;
                        
                        if ($w_rate2 == 0) {
                            return response()->json([
                                'success' => false,
                                'message' => "Second Life Rate Not Found! Please Check... For Policy Term: $term"
                            ]);
                        }
                        
                        $w_rate3 = ($dblPrmRate + $w_rate2) * 0.90;
                        $w_temp2 = ($w_rate3 / $dblRateBasis) * $sa;
                        
                        $plan_rider = $this->smartlife_db->table('plan_rider_config as prc')
                            ->join('rider_info as ri', 'ri.rider_code', '=', 'prc.rider_code')
                            ->where('prc.plan_code', $plan_code)
                            ->where('ri.ptd', true)
                            ->where('prc.IsAutomatic', true)
                            ->first();
                            
                        if ($plan_rider) {
                            $w_temp3 = (($plan_rider->rate * 2) / $plan_rider->rate_basis) * $sa * $term;
                            $PTDRiderPrem = $w_temp3;
                            $total_rider_prem = $w_temp3;
                        }
                        
                        $w_temp = ($w_temp2 + $w_temp3 + $PolicyFeeVAR) * $TaxVAR;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Rate Not Found! Please Check...'
                        ]);
                    }
                }
            } else if ($CreditLifeProduct == true) {
                $w_temp = $dblPrmRate * $sa;
            }
            
            $basic_premVAR = $w_temp - ($TaxVAR + $PolicyFeeVAR + $total_rider_prem);
            $modal_prem = $w_temp;
            
            $premDiscount = 0;
            if ($DiscountRate > 0) {
                $premDiscount = round(($DiscountRate / 100) * $basic_premVAR, 2);
            }
            
            $TotalPremium = round($w_temp - $premDiscount, 2);
            
            // $ReinsuranceAlert = false;
            // $totalSA = 0;
            
            // if ($client_number) {
            //     $totalSA_result = $this->smartlife_db->table('proposalinfo')
            //         ->where('client_number', $client_number)
            //         ->sum('Sum_Assured');
            //     $totalSA = round($totalSA_result ?? 0, 2);
            // }
            
            // if ($ReinsuranceMinSA > 0) {
            //     if (($sa + $totalSA) > $ReinsuranceMinSA) {
            //         $ReinsuranceAlert = true;
            //     }
            // }
            
            $res = array(
                'success' => true,
                'basic_prem' => number_format((float)$basic_premVAR, 2, '.', ''),
                'modal_prem' => number_format((float)$modal_prem, 2, '.', ''),
                'policyFee' => number_format((float)$PolicyFeeVAR, 2, '.', ''),
                'Vat' => number_format((float)$TaxVAR, 2, '.', ''),
                'total_rider_prem' => number_format((float)$total_rider_prem, 2, '.', ''),
                'PTDRiderPrem' => number_format((float)$PTDRiderPrem, 2, '.', ''),
                'premDiscount' => number_format((float)$premDiscount, 2, '.', ''),
                'TotalPremium' => number_format((float)$TotalPremium, 2, '.', ''),
                'Prem_rate' => $dblPrmRate,
                'rate_basis' => $dblRateBasis,
                //'ReinsuranceAlert' => $ReinsuranceAlert,
                'loading_factor' => $loadingFactorVAR,
                'message' => 'Premium Calculated Successfully!'
            );

        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //lets create function OrdinaryPolicies
    public function OrdinaryPolicies(Request $request){
        try {
            $res = array();
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $pay_mode_id = $request->input('pay_mode_id'); //id knows if its monthly, quarterly....
            /*
            Premium (Yearly) = (((rate/1000) * sum_assured) + 50) * 1.01

            Premium (Quarterly) = ((((rate/1000) * sum_assured + 50)*0.26) *1.01

            Premium (Half-Yearly) = ((((rate/1000) * sum_assured +50) * 0.51) *1.01

            Premium (Monthly) = ((((rate/1000) * sum_assured + 50) * 0.0875) *1.01
            50 is the policy fee in table planinfo
            0.26 is the loading in table paymentmodeinfo
            1.01 is the VAT in table planinfo
            */
            $basic_premium = 0;
            $rate = DbHelper::getColumnValue('premium_rate_setup', 'plan_code', $plan_code, 'rate') ?? 0;
            $rate_basis = DbHelper::getColumnValue('premium_rate_setup', 'plan_code', $plan_code, 'rate_basis') ?? 1;
            $policyFee = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'policyFee') ?? 50;
            $loading = DbHelper::getColumnValue('paymentmodeinfo', 'id', $pay_mode_id, 'loadingfactor') ?? 1;
            $vat = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'TaxRate') ?? 1.01;

            $basic_premium = (($rate / $rate_basis) * $sum_assured);
            $premium = (($basic_premium + $policyFee) * $loading) * $vat;
            $res = array(
                'success' => true,
                'premium' => (float)number_format((float) $premium, 2, '.', ''),
                'basic_premium' => (float)number_format((float) $basic_premium, 2, '.', ''),
                'policyFee' => (float)$policyFee,
                'loading' => (float)$loading,
                'vat' => (float)$vat,
                'message' => 'Premium Calculated Successfully!!'
            );


            
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    public function esb_manual_rider(Request $request)
    {
        try {

            $sa = $request->input('sa');
            $class_code = $request->input('class_code');
            $rider_code = $request->input('rider_code');

            //fetch rate & rate_basis...
            $rate = DbHelper::getColumnValue('paclass', 'class_code', $class_code, 'rate');
            $rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code', $rider_code, 'rate_basis');
            $premium = ($rate * (float)$sa) / $rate_basis;
            $res = array(
                'success' => true,
                'premium' => number_format((float) $premium, 2, '.', ''),
                'message' => 'Rider Premium Calculated Successfully!!'
            );



            return response()->json($res);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //TODO - Do calculations for life & micro products
    //1. ESB
    public function esbcalculation(Request $request)
    {
        try {

            $isForParliament = $request->input('isForParliament');
            if(isset($isForParliament)){
                $isForParliament = 0;
            }
            $total_premium = $request->input('total_premium');
            $PlanCode = 2; //$request->input('PlanCode');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $gender = $request->input('gender');
            $class_code = $request->input('class_code');
            $rider_array = array();

            $pol_fee = DbHelper::getColumnValue('planinfo', 'PlanOldName', $PlanCode, 'policy_fee');

            $inv_prem = 0;
            $transfer_charge = 0;
            $rider_prem = 0;
            $tmp_risk = 0;

            if ($isForParliament == 1) {
                $term_rider_prem = $total_premium;
                $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='03' AND PlanCode='$PlanCode' AND age<='$anb' AND age2>='$anb') AND ('$term' between term_from and term_to)";

                $r_rows = DbHelper::getTableRawData($sql);

                if (sizeof($r_rows) > 0) {
                    $rider_rate = $r_rows[0]->normal_rate;
                    $rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code', '03', 'rate_basis');
                    $term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;
                    // $rider_array[] = array(
                    //     'r_rider' => "03",
                    //     'r_sa' => number_format((float) $term_rider_sa, 2, '.', ''),
                    //     'r_premium' => number_format((float) $term_rider_prem, 2, '.', ''),
                    // );
                }

                $transfer_charge = 0.05 * $total_premium;
                $sum_assured = $term_rider_sa;
            } else {
                $inv_prem = 0.7 * $total_premium;
                $transfer_charge = 0.05 * $total_premium;
                $rider_prem = 0;
                $tmp_risk = 0.3 * $total_premium;

                //prem 
                //$sql = "SELECT * FROM premdistinfo WHERE PlanCode='$PlanCode'  AND ( '$total_premium' BETWEEN MinPrem AND MaxPrem)";
                // $sql = "SELECT * FROM rider_premuim_rates WHERE rider_code='3'  AND 
                //  PlanCode='$PlanCode'  AND 
                //  ($anb BETWEEN age AND age2) AND ($term between term_from and term_to)";

                //$prem_rows = DbHelper::getTableRawData($sql);
                //if (sizeof($prem_rows) > 0) {
                //calculate transfer charge
                //$transfer_charge = ($prem_rows[0]->TransferRate / 100)*$total_premium;
                $rider_prem = $tmp_risk - $pol_fee - $transfer_charge;
                //}
                $term_rider_prem = 0.5 * $rider_prem;
                $hci_rider_prem = 0.33 * $rider_prem;
                $ai_rider_prem = 0.17 * $rider_prem;

                $sum_assured = 0;

                $term_rider_sa = 0;
                $hci_rider_sa = 0;
                $ai_rider_sa = 0;

                //term rider
                $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='03' AND PlanCode='$PlanCode' AND age<='$anb' AND age2>='$anb') AND ('$term' between term_from and term_to)";

                $r_rows = DbHelper::getTableRawData($sql);

                if (sizeof($r_rows) > 0) {
                    $rider_rate = $r_rows[0]->normal_rate;
                    $rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code', '03', 'rate_basis');
                    $term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;
                    $rider_array[] = array(
                        'r_rider' => "03",
                        'r_sa' => number_format((float) $term_rider_sa, 2, '.', ''),
                        'r_premium' => number_format((float) $term_rider_prem, 2, '.', ''),
                    );

                    //hci
                    $sql = "SELECT * FROM rider_premuim_rates WHERE rider_code='02' AND age='$anb' AND PlanCode='$PlanCode' ";
                    $r_rows = DbHelper::getTableRawData($sql);

                    if (sizeof($r_rows) > 0) {
                        if ($gender == "M") {
                            $hci_rider_rate = $r_rows[0]->normal_rate;
                        }
                        if ($gender == "F") {
                            $hci_rider_rate = $r_rows[0]->female_rate;
                        }
                        $hci_rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code', '02', 'rate_basis');
                        $hci_rider_sa = ($hci_rider_prem / $hci_rider_rate) * $hci_rider_rate_basis;
                        $rider_array[] = array(
                            'r_rider' => "02",
                            'r_sa' => number_format((float) $hci_rider_sa, 2, '.', ''),
                            'r_premium' => number_format((float) $hci_rider_prem, 2, '.', ''),
                        );
                    }

                    //AI
                    $ai_rider_rate = DbHelper::getColumnValue('paclass', 'class_code', $class_code, 'rate');
                    $ai_rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code', '01', 'rate_basis');
                    $ai_rider_sa = ($ai_rider_prem / $ai_rider_rate) * $ai_rider_rate_basis;
                    $rider_array[] = array(
                        'r_rider' => "01",
                        'r_sa' => number_format((float) $ai_rider_sa, 2, '.', ''),
                        'r_premium' => number_format((float) $ai_rider_prem, 2, '.', ''),
                    );

                    //Also add up the sum assured to the sa value
                    $sum_assured = $term_rider_sa + $hci_rider_sa + $ai_rider_sa;
                    if ($term_rider_sa == 0 || $term_rider_sa == '0') {
                        //alert(inv_prem);
                        $inv_prem = $inv_prem + $term_rider_prem;
                        $rider_prem = $rider_prem - $term_rider_prem;
                    }
                    if ($hci_rider_sa == 0 || $term_rider_sa == '0') {
                        //alert(inv_prem);
                        $inv_prem = $inv_prem + $hci_rider_prem;
                        $rider_prem = $rider_prem - $hci_rider_prem;
                    }
                }
            }


            $res = array(
                'success' => true,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'policy_fee' => number_format((float) $pol_fee, 2, '.', ''),
                'inv_prem' => number_format((float) $inv_prem, 2, '.', ''),
                'rider_prem' => number_format((float) $rider_prem, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                'riders' => $rider_array,
                'message' => 'ESB Premiums calculated Successfully!!'
            );
            return response()->json($res);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //2. GEEP
    public function GEEP(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $monthly_premium = $request->input('monthly_premium');
            $total_premium = 0;
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $plan_premium_table = '001';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }
            $total_premium = $monthly_premium * $payment_factor;


            $divided_premium = $total_premium / $payment_factor;
            $res = array();
            $plan_id = $plan_code; //DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            $sql_query = "SELECT * FROM premdistinfo WHERE (PlanCode=$plan_id)  AND ($divided_premium BETWEEN MinPrem AND MaxPrem)";


            $results_prem = DbHelper::getTableRawData($sql_query);
            //$results_prem = $qry->first();
            if ($results_prem) {
                $investment_per = $results_prem[0]->InvestmentRate;
                $rider_per = $results_prem[0]->ProtectionRate;
                $protection_rate = $results_prem[0]->ProtectionRate;
                $cepa_rate = $results_prem[0]->CepaRate;
                $TransferRate = $results_prem[0]->TransferRate;
                //number_format((float)$foo, 2, '.', '');
                $protection_prem = number_format((float) ($rider_per / 100) * $total_premium, 2, '.', '');
                $inv_prem = number_format((float) ($investment_per / 100) * $total_premium, 2, '.', '');
                $inv_prem -= $pol_fee;
                $cepa_prem = number_format((float) ($cepa_rate / 100) * $total_premium, 2, '.', '');
                $transfer_prem = number_format((float) ($TransferRate / 100) * $total_premium, 2, '.', '');

                $prem_rate_qry = "SELECT * FROM premium_rate_setup WHERE plan_code='$plan_id' AND age='$anb' AND table_code='1' AND term='$term' ";
                $result_prem_rate = DbHelper::getTableRawData($prem_rate_qry);
                if ($result_prem_rate) {
                    $rider_prem = number_format((float) ($protection_rate / 100) * $divided_premium, 2, '.', '');
                    $sum_assured = number_format((float) ($rider_prem * $plan_rate_basis) / $result_prem_rate[0]->rate, 2, '.', '');
                }

                //get the loading factor
                $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
                $results_paymode = $qry->first();
                $loadingfactor = $results_paymode->loadingfactor;

                $total_premium *= number_format((float) $loadingfactor, 2, '.', '');
            }

            //if success it will return premium per month plus suspense account.
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'Protection_premium' => $protection_prem,
                'Inv_prem' => $inv_prem,
                'Cepa_prem' => $cepa_prem,
                'Transfer_charge' => $transfer_prem,
                'Total_Premium' => $total_premium,
                'Sum_Assured' => $sum_assured,
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }
    //3. Life Savings
    public function lifeSavingsPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $plan_premium_table = 2; //'002';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }


            $res = array();
            $plan_id = $plan_code; //DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 
            $qry = $this->smartlife_db->table('premium_rate_setup as p')
                ->select('*')
                ->where(
                    array(
                        'p.plan_code' => $plan_id,
                        'p.age' => $anb,
                        'p.term' => $term,
                        'p.table_code' => $plan_premium_table
                    )
                );
            $results_prem = $qry->first();
            if ($results_prem) {
                $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
                $results_paymode = $qry->first();

                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $w_rate2 = $results_prem->rate;
                $w_cover_period = $results_paymode->coverperiod;

                $w_tmp = ($w_rate2 / $plan_rate_basis) * $sum_assured;
                $basic_premium = $w_tmp * $w_cover_period;
                $modal_prem = ($basic_premium + $rider_prem + $pol_fee) * $loadingfactor;
            }

            //if success it will return premium per month plus suspense account.
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'basic_premium' => number_format((float) $basic_premium, 2, '.', ''),
                'modal_prem' => number_format((float) $modal_prem, 2, '.', ''),
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    //4. Family Comprehension
    public function FamilyComprehensionPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'premium_table');
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }


            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();

            //$plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            if ($results_paymode) {
                //
                $CategoryCode = DbHelper::getColumnValue('RelationshipCategory', 'description', $relationship_code, 'id');
                $qry = $this->smartlife_db->table('premium_rate_setup as p')
                    ->select('*')
                    ->where(
                        array(
                            'p.plan_code' => $plan_code,
                            'p.age' => $anb,
                            'p.term' => $term,
                            'p.table_code' => $plan_premium_table
                        )
                    );
                $results_prem = $qry->first();



                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (($funeral_rate / $funeral_rate_basis) * ($sum_assured * $funeral_cover_period));
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;
                $transfer_charge = 0.05 * $premiumVAR;
                $premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    //4. Personal Accident
    public function PersonalAccidentPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'premium_table');
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }


            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();

            //$plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            if ($results_paymode) {
                //
                $CategoryCode = DbHelper::getColumnValue('RelationshipCategory', 'description', $relationship_code, 'id');
                $qry = $this->smartlife_db->table('premium_rate_setup as p')
                    ->select('*')
                    ->where(
                        array(
                            'p.plan_code' => $plan_code,
                            'p.age' => $anb,
                            'p.term' => $term,
                            'p.table_code' => $plan_premium_table
                        )
                    );
                $results_prem = $qry->first();



                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (($funeral_rate / $funeral_rate_basis) * ($sum_assured * $funeral_cover_period));
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;
                $transfer_charge = 0.05 * $premiumVAR;
                $premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    //-Critical Illness Plan
    public function CriticalIllnessPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            $CategoryCode = $relationship_code;

            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = '002';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }


            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();

            //$plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            if ($results_paymode) {
                //
                //$CategoryCode = DbHelper::getColumnValue('RelationshipCategory', 'description',$relationship_code,'id');
                $qry = $this->smartlife_db->table('funeralratesinfo as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_code, 'p.CategoryCode' => $CategoryCode, 'p.Min_age' => $anb))->orderBy('id', 'desc');
                $results_prem = $qry->first();



                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->Rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (($funeral_rate / $funeral_rate_basis) * ($sum_assured * $funeral_cover_period));
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;
                //$transfer_charge = 0.05 * $premiumVAR;
                //$premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    //4. Funeral-ideal
    public function IdealFuneralPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            $CategoryCode = $relationship_code;

            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = '002';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }


            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();

            //$plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            if ($results_paymode) {
                //
                //$CategoryCode = DbHelper::getColumnValue('RelationshipCategory', 'description',$relationship_code,'id');
                $qry = $this->smartlife_db->table('funeralratesinfo as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_code, 'p.CategoryCode' => $CategoryCode, 'p.Min_age' => $anb))->orderBy('id', 'desc');
                $results_prem = $qry->first();



                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->Rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (($funeral_rate / $funeral_rate_basis) * ($sum_assured * $funeral_cover_period));
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;

                //get the transfer charge and the new premium amount
                $transfer_charge = 0.05 * $premiumVAR;
                $premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }
    //5. Funeral-premium
    public function PremiumFuneralPlan(Request $request)
    {
        try {
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = '001';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }
            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.OldPlanCode' => '29', 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();
            if ($results_paymode) {
                //,'p.tableCode' => '002'
                $qry = $this->smartlife_db->table('funeralratesinfo as p')
                    ->select('*')
                    ->where(array('p.plan_code' => 37, 'p.CategoryCode' => $relationship_code, 'p.Min_age' => $anb));
                $results_prem = $qry->first();

                //Do the calculations here
                $loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->Rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (($funeral_rate / $funeral_rate_basis) * ($sum_assured * $funeral_cover_period));
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;
                $transfer_charge = 0.05 * $premiumVAR;
                $premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }


    //Dependants Anidaso
    public function DPAnidaso(Request $request)
    {
        try {

            $total_premium = $request->input('total_premium');
            $PlanCode = 10; //$request->input('PlanCode');
            $plan_id = 10;
            $anb = $request->input('anb');
            $term = $request->input('term');
            //$gender = $request->input('gender'); 
            //$class_code = $request->input('class_code'); 
            $rider_array = array();

            $pol_fee = DbHelper::getColumnValue('planinfo', 'PlanOldName', $PlanCode, 'policy_fee');

            $sum_assured = 0;
            $inv_prem = 0;
            $transfer_charge = 0;
            $rider_prem = 0;
            $tmp_risk = 0;

            //TR...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='03' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '03'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $term_rider_rate = $results_prem->rate;
                    $tr_rate_basis = $results_prem->rate_basis;
                }

                $term_rider_sa = $r_rows[0]->SumAssured;
                $term_rider_prem = ($term_rider_sa * $term_rider_rate) / $tr_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "03",
                    'r_sa' => number_format((float) $term_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $term_rider_prem, 2, '.', ''),
                );
            }


            //AI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='01' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '01'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $ai_rider_rate = $results_prem->rate;
                    $ai_rate_basis = $results_prem->rate_basis;
                }

                $ai_rider_sa = $r_rows[0]->SumAssured;
                $ai_rider_prem = ($ai_rider_sa * $ai_rider_rate) / $ai_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "01",
                    'r_sa' => number_format((float) $ai_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $ai_rider_prem, 2, '.', ''),
                );
            }

            //HCI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='02' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '02'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $hci_rider_rate = $results_prem->rate;
                    $hci_rate_basis = $results_prem->rate_basis;
                }

                $hci_rider_sa = $r_rows[0]->SumAssured;
                $hci_rider_prem = ($hci_rider_sa * $hci_rider_rate) / $hci_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "02",
                    'r_sa' => number_format((float) $hci_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $hci_rider_prem, 2, '.', ''),
                );
            }


            $rider_prem = $term_rider_prem + $ai_rider_prem + $hci_rider_prem;
            $inv_prem = $total_premium - $rider_prem;
            $sum_assured = $term_rider_sa + $ai_rider_sa + $hci_rider_sa;

            $res_msg = "For GHc $total_premium benefits \nDeath (Policy Holder): Ghc$term_rider_sa \nAccident (Pol Holder): Ghc$ai_rider_sa \nHCI (Policy Holder): Ghc$hci_rider_sa";


            $res = array(
                'success' => true,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'policy_fee' => number_format((float) $pol_fee, 2, '.', ''),
                'inv_prem' => number_format((float) $inv_prem, 2, '.', ''),
                'rider_prem' => number_format((float) $rider_prem, 2, '.', ''),
                'total_premium' => number_format((float) $total_premium, 2, '.', ''),
                //'transfer_charge' => number_format((float)$transfer_charge, 2, '.', ''),
                'riders' => $rider_array,
                'message' => 'Anidaso Premiums calculated Successfully!!',
                'res_msg' => $res_msg
            );
            return response()->json($res);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //ITCAnidaso
    public function ITCAnidaso(Request $request)
    {
        try {
            $PlanCode = 51;
            $formatted_ptd_sa = '7,000';
            $sum_assured = number_format((float) DbHelper::getColumnValue('planinfo', 'plan_code', $PlanCode, 'SumAssuredRate'), 2, '.', '');
            $total_premium = number_format((float) DbHelper::getColumnValue('planinfo', 'plan_code', $PlanCode, 'rate_basis') + (float) DbHelper::getColumnValue('planinfo', 'plan_code', $PlanCode, 'policy_fee'), 2, '.', '');

            $res_msg = "For GHc $total_premium benefits \nDeath (Policy Holder): Ghc$sum_assured \nTotal Permanent Disability (Pol Holder): Ghc$formatted_ptd_sa and E-Health Services";


            $res = array(
                'success' => true,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'total_premium' => number_format((float) $total_premium, 2, '.', ''),
                'message' => 'Anidaso Premiums calculated Successfully!!',
                'res_msg' => $res_msg
            );
            return response()->json($res);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //Anidaso
    public function LifeAnidaso(Request $request)
    {
        try {

            $total_premium = $request->input('total_premium');
            $PlanCode = 10; //$request->input('PlanCode');
            $plan_id = 10;
            $anb = $request->input('anb');
            $term = $request->input('term');
            //$gender = $request->input('gender'); 
            //$class_code = $request->input('class_code'); 
            $rider_array = array();

            $pol_fee = DbHelper::getColumnValue('planinfo', 'plan_code', $PlanCode, 'policy_fee');

            $sum_assured = 0;
            $inv_prem = 0;
            $transfer_charge = 0;
            $rider_prem = 0;
            $tmp_risk = 0;

            //TR...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='03' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '03'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $term_rider_rate = $results_prem->rate;
                    $tr_rate_basis = $results_prem->rate_basis;
                }

                $term_rider_sa = $r_rows[0]->SumAssured;
                $term_rider_prem = ($term_rider_sa * $term_rider_rate) / $tr_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "03",
                    'r_sa' => number_format((float) $term_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $term_rider_prem, 2, '.', ''),
                );
            }


            //AI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='01' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '01'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $ai_rider_rate = $results_prem->rate;
                    $ai_rate_basis = $results_prem->rate_basis;
                }

                $ai_rider_sa = $r_rows[0]->SumAssured;
                $ai_rider_prem = ($ai_rider_sa * $ai_rider_rate) / $ai_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "01",
                    'r_sa' => number_format((float) $ai_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $ai_rider_prem, 2, '.', ''),
                );
            }

            //HCI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='02' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '02'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $hci_rider_rate = $results_prem->rate;
                    $hci_rate_basis = $results_prem->rate_basis;
                }

                $hci_rider_sa = $r_rows[0]->SumAssured;
                $hci_rider_prem = ($hci_rider_sa * $hci_rider_rate) / $hci_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "02",
                    'r_sa' => number_format((float) $hci_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $hci_rider_prem, 2, '.', ''),
                );
            }


            $rider_prem = $term_rider_prem + $ai_rider_prem + $hci_rider_prem;
            $inv_prem = $total_premium - $rider_prem;
            $sum_assured = $term_rider_sa + $ai_rider_sa + $hci_rider_sa;

            $formatted_total_premium = number_format($total_premium, 2, '.', '');
            $formatted_term_rider_sa = number_format($term_rider_sa, 2, '.', '');
            $formatted_ai_rider_sa = number_format($ai_rider_sa, 2, '.', '');
            $formatted_hci_rider_sa = number_format($hci_rider_sa, 2, '.', '');


            $res_msg = "For GHc $formatted_total_premium benefits \nDeath (Policy Holder): Ghc$formatted_term_rider_sa \nAccident (Pol Holder): Ghc$formatted_ai_rider_sa \nHCI (Policy Holder): Ghc$formatted_hci_rider_sa";


            $res = array(
                'success' => true,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'policy_fee' => number_format((float) $pol_fee, 2, '.', ''),
                'inv_prem' => number_format((float) $inv_prem, 2, '.', ''),
                'rider_prem' => number_format((float) $rider_prem, 2, '.', ''),
                'total_premium' => number_format((float) $total_premium, 2, '.', ''),
                //'transfer_charge' => number_format((float)$transfer_charge, 2, '.', ''),
                'riders' => $rider_array,
                'message' => 'Anidaso Premiums calculated Successfully!!',
                'res_msg' => $res_msg
            );
            return response()->json($res);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }


    public function DepAnidaso(Request $request)
    {
        try {
            $plan_code = $request->input('plan_code');
            $plan_id = $plan_code;
            $sum_assured = $request->input('sum_assured');
            $relationship_code = $request->input('relationship_code');
            $CategoryCode = DbHelper::getColumnValue('relationship_mainteinance', 'code', $relationship_code, 'CategoryCode');

            $dp_premium = 0;
            $funeral_rate = 0;
            $tr_rate_basis = 0;


            $qry = $this->smartlife_db->table('plan_rider_config as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '03'));
            $results_prem = $qry->first();
            if ($results_prem) {
                $tr_rate_basis = $results_prem->rate_basis;
            }

            $qry = $this->smartlife_db->table('funeralratesinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_id, 'p.CategoryCode' => $CategoryCode));
            $results_funeral = $qry->first();
            if ($results_funeral) {
                echo $funeral_rate = $results_funeral->Rate;
            }

            if ($CategoryCode == "1") {
                //self
                $sum_assured = $sum_assured;
            } else if ($CategoryCode == "2") {
                //wife/husband
                $sum_assured = $sum_assured;
            } else if ($CategoryCode == "3") {
                //child
                $sum_assured = ((float) $sum_assured / 2);
            } else if ($CategoryCode == "4") {
                $sum_assured = 1000;
            } else {
                $msg = "Not Applicable";
            }

            $dp_premium = ((float) $sum_assured * $funeral_rate) / $tr_rate_basis;

            //if success it will return premium per month plus suspense account.
            $res = array(
                'success' => true,
                'dp_sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'dp_premium' => number_format((float) $dp_premium, 2, '.', ''),
                'message' => 'Success'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //HCI
    public function HCIPlan(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = 0; //$request->input('sum_assured');
            $anb = $request->input('anb');
            $term = $request->input('term');
            $paymode = $request->input('paymode');
            $relationship_code = $request->input('relationship_code');
            $PackageCode = $request->input('PackageCode');
            $CategoryCode = $relationship_code;

            //$this->getRelatioshipCategory($request->input('relationship_code'));
            if ($relationship_code == null) {
                $res = array(
                    'success' => true,
                    'message' => 'Not allowed for selected relationship'
                );
                return response()->json($res);
            }
            $plan_premium_table = '002';
            $plan_rate_basis = 1000;
            $rider_prem = 0;
            $pol_fee = 1;
            $payment_factor = 1;
            $hci_sa = 0;

            if ($paymode == "M") {
                $payment_factor = 1;
                $pol_fee *= 1;
            }
            if ($paymode == "Q") {
                $payment_factor = 3;
                $pol_fee *= 3;
            }
            if ($paymode == "H") {
                $payment_factor = 6;
                $pol_fee *= 6;
            }
            if ($paymode == "Y") {
                $payment_factor = 12;
                $pol_fee *= 12;
            }

            $hci_sum_assured = 0;
            $days_covered = 0; //FLIC.BaPackages[z].Duration;
            $tmpsum_assured = 0;
            $hci_sa = 0; //FLIC.BaPackages[z].hci_sum;


            $res = array();
            $qry = $this->smartlife_db->table('paymentmodeinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code, 'p.OldPayMode' => $paymode));
            $results_paymode = $qry->first();


            $qry = $this->smartlife_db->table('bapackages as p')
                ->select('*')
                ->where(array('p.id' => $PackageCode));
            $results_bapackages = $qry->first();
            if ($results_bapackages) {
                $days_covered = $results_bapackages->Duration;
                $hci_sa = $results_bapackages->hci_sum;
                //$hci_sa = (float)$results_bapackages->hci_sum / (float)$results_bapackages->Duration;
                if ((int)$CategoryCode == 3 || (int)$CategoryCode == 4) {
                    if ((int)$CategoryCode == 3) {
                        $hci_sum_assured = ((float)$results_bapackages->hci_sum * (float)$results_bapackages->Duration * (float)$results_bapackages->childPerc) / 100;
                    } else if ((int)$CategoryCode == 4) {
                        $hci_sum_assured = ((float)$results_bapackages->hci_sum * (float)$results_bapackages->Duration * (float)$results_bapackages->parentPerc) / 100;
                    }
                } else {
                    if ((int)$CategoryCode == 1 || (int)$CategoryCode == 2) {
                        $hci_sum_assured = (float)$results_bapackages->hci_sum * (float)$results_bapackages->Duration;
                        if ((int)$CategoryCode == 1) {
                            $sum_assured = (float)$results_bapackages->sa * (float)$hci_sum_assured;
                        }
                    }
                }
            }


            //$plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            if ($results_paymode) {
                //
                //$CategoryCode = DbHelper::getColumnValue('RelationshipCategory', 'description',$relationship_code,'id');
                $criteria_arr = array(
                    'p.plan_code' => $plan_code,
                    'p.CategoryCode' => $CategoryCode,
                    'p.Min_age' => $anb,
                    'p.tableCode' => 2
                );
                if ((int)$CategoryCode != 3) $criteria_arr['p.term'] = $term;
                $qry = $this->smartlife_db->table('funeralratesinfo as p')
                    ->select('*')
                    ->where($criteria_arr)->orderBy('id', 'desc');
                $results_prem = $qry->first();

                //Do the calculations here
                //$loadingfactor = $results_paymode->loadingfactor;
                $funeral_cover_period = $results_paymode->coverperiod;

                $funeral_rate = $results_prem->Rate;
                $funeral_rate_basis = 1000;

                $premiumVAR = (((float)$hci_sa * (float)$days_covered * (float)$funeral_rate) / (float)$funeral_rate_basis) * $payment_factor;
                //$premiumVAR = ($premiumVAR + $pol_fee) * $loadingfactor;
                $transfer_charge = 0.05 * $premiumVAR;
                $premiumVAR = $premiumVAR - $transfer_charge;
            }

            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'hci_sum_assured' => number_format((float) $hci_sum_assured, 2, '.', ''),
                'days_covered' => number_format((float) $days_covered, 2, '.', ''),
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'premium' => number_format((float) $premiumVAR, 2, '.', ''),
                'transfer_charge' => number_format((float) $transfer_charge, 2, '.', ''),
                //'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    //ComputePremiumCreditLife
    public function ComputePremiumCreditLife(Request $request)
    {
        try {
            //
            $plan_code = $request->input('plan_code');
            $age = $request->input('anb');
            $term_of_policy = $request->input('term');
            $sa = (float)$request->input('sa');
            $paymode = $request->input('paymode');
            $extra_prem = 0; //$request->input('paymode');
            $IsCreditlifeTopUp = false; //$request->input('IsCreditlifeTopUp');
            $UseCustomPremRate = false; //$request->input('UseCustomPremRate');
            $DiscountRate = 0;
            //
            $mortgage = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'mortgage');
            $is_keyman = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'is_keyman');
            $IsLoanProtection = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'IsLoanProtection');
            $rate_basis = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'rate_basis');
            $premium_table = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'premium_table');
            $policyFee = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'policy_fee');
            $Is5YearTerm = DbHelper::getColumnValue('paymentmodeinfo', 'id', $paymode, 'Is5YearTerm');

            //$age = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'rate_basis');

            $CostOfProperty = $request->input('CostOfProperty');


            $dblPrmRate = 0;
            $tempPrem = 0;
            $dblReinBenefit = 0;
            $dblReinPrem = 0;
            if ($mortgage == true || $is_keyman == true || $IsLoanProtection == true) {
                if ($mortgage) {
                    if ((float)$CostOfProperty < 0) {
                        $res = array(
                            'success' => false,
                            'message' => 'Property Cost Is Mandatory ! '
                        );
                        return response()->json($res);
                    }
                }
                if ((float)$rate_basis <= 0) {
                    $res = array(
                        'success' => false,
                        'message' => 'Rate Basis Is Mandatory ! Check Product Details'
                    );
                    return response()->json($res);
                } else {
                    //$UseCustomPremRate = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'UseCustomPremRate');
                    if (isset($UseCustomPremRate) && $UseCustomPremRate == false) {
                        //$IsCreditlifeTopUp = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'IsCreditlifeTopUp');
                        if ($IsCreditlifeTopUp == false) {
                            $criteria_arr = array(
                                'p.plan_code' => $plan_code,
                                'p.age' => $age,
                                'p.table_code' => $premium_table,
                                'p.term' => $term_of_policy
                            );
                            $sQuery = $this->smartlife_db->table('premium_rate_setup as p')
                                ->select('*')
                                ->where($criteria_arr)->orderBy('id', 'desc');
                        } else if (isset($UseCustomPremRate) && $IsCreditlifeTopUp == true) {
                            //is its five years you cap it to a term of 5....
                            $criteria_arr = array(
                                'p.plan_code' => $plan_code,
                                'p.age' => $age,
                                'p.table_code' => $premium_table,
                                'p.term' => 5
                            );
                            $sQuery = $this->smartlife_db->table('premium_rate_setup as p')
                                ->select('*')
                                ->where($criteria_arr)->orderBy('id', 'desc');
                        }

                        $_dataSet = $sQuery->first(); //DbHelper::getTableRawData($sQuery);
                        if (isset($_dataSet)) {
                            //MsgCtrlFlag = false;
                            $recRow = $_dataSet;
                            $dblPrmRate = (float)$recRow->rate;
                            $Prem_rate = $dblPrmRate; //

                            if ($Prem_rate == 0) {
                                $res = array(
                                    'success' => false,
                                    'message' => 'Rate Not Found ! Please Check ...'
                                );
                                return;
                            }
                        } else {
                            $res = array(
                                'success' => false,
                                'message' => 'Rate Not Found ! Please Check ...'
                            );
                            return;
                        }
                    } else if ($UseCustomPremRate == true) {
                        $dblPrmRate = $Prem_rate;
                        if ($Prem_rate == 0) {
                            $res = array(
                                'success' => false,
                                'message' => 'Custom Premium Rate Has Not Been Specified ! Please Check ...'
                            );
                            return;
                        }
                    }
                    //
                    //
                    $dblProrata = 0;
                    //*******************************
                    $DurationDays = 365;
                    if ($sa > 0) { //
                        $IsLoanProtection = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'IsLoanProtection');

                        if ($IsLoanProtection == false && $Is5YearTerm == false) {
                            $w_rate2 = $dblPrmRate;
                            $dblRateBasis = $rate_basis;
                            $w_temp = ($w_rate2 / $dblRateBasis) * $sa;
                            $basic_prem = $w_temp; //it was this.

                            //$policyFee is from planinfo 
                            //$sa*sum assured) is passed by the client
                            $policyFeeVAR = (($sa * $policyFee) / $dblRateBasis); // get policy fee...
                            $TotalPrmExpectedVAR = $basic_prem + $policyFee;
                            $modal_premVAR = $TotalPrmExpectedVAR;
                            $dblProrata = $DurationDays / 365;
                            $policyFee = $dblProrata * $policyFeeVAR;
                            //this.TotalPrmExpected = Math.Round(dblProrata * TotalPrmExpectedVAR, 2);
                            $basic_prem = $dblProrata * $w_temp;
                            if ($DiscountRate > 0) { //if We have discount
                                $premDiscount = ($DiscountRate / 100) * $basic_prem;
                            } else {
                                $PremDiscount = 0;
                            }
                            //
                            $TotalPrmExpected = $basic_prem + $policyFee;

                            //
                            $modal_prem = (($dblProrata * $w_temp) + $policyFee);
                        } else if ($IsLoanProtection == true ||  ($mortgage == true || $is_keyman == true) && $Is5YearTerm == true) {
                            //Loan Protection
                            $w_rate2 = $dblPrmRate;
                            $modal_prem = ($dblPrmRate / 100) * $sa; // Get premium Here
                            $basic_prem = $modal_prem;
                            if ($DiscountRate > 0) { //if We have discount
                                $premDiscount = ($DiscountRate / 100) * $basic_prem;
                            } else {
                                $PremDiscount = 0;
                            }
                            //
                            $TotalPrmExpected = $basic_prem + $policyFee;
                            //
                            $modal_prem = ($TotalPrmExpected);
                        }
                    } // END OF   if (this.sa > 0)
                }
                //
                $TotalPremium = $modal_prem + $extra_prem - $PremDiscount;
            }





            //if success it will return premium per month plus suspense account.
            //ba_package, hci_sum_assured, days_covered, sum_assured, premium
            $res = array(
                'success' => true,
                'policy_fee' => $policyFee,
                'premium' => number_format((float) $TotalPremium, 2, '.', ''),
                'PremDiscount' => number_format((float) $PremDiscount, 2, '.', ''),
                //'loadingfactor' => $loadingfactor,
                'message' => 'Premium Calculated successfully'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }


    //6. LifeAnidaso
    /*
    public function LifeAnidaso(Request $request)
    {
        try{
            //
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $plan_premium_table = 1;
            $plan_rate_basis = DbHelper::getColumnValue('planinfo','PlanOldName',$plan_code,'rate_basis');
            $life_premium = 0;
            $pol_fee  = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'policy_fee');
            
            
            $res = array();
            $plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 
            $qry = $this->smartlife_db->table('premium_rate_setup as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_id,'p.age' => $anb,
                'p.table_code' => $plan_premium_table));
            $results_prem = $qry->first();
            if($results_prem){
                //let life_prem = (parseFloat(viewModel.sum_assured()) * parseFloat(results.rows.item(0).rate)) / 1000;
                $life_premium = ($sum_assured * $results_prem->rate) / $plan_rate_basis;
            }
            
            //if success it will return premium per month plus suspense account.
            $res = array(
                'success' => true,
                'policy_fee' => $pol_fee,
                'life_premium' => number_format((float)$life_premium, 2, '.', ''),
                'message' => 'Premium Calculated successfully'
            );	
        }catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        }catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }
    */
    //7. Anidaso
    /*
    public function DepAnidaso(Request $request)
    {
        try{
            $plan_code = $request->input('plan_code');
            $sum_assured = $request->input('sum_assured');
            $anb = $request->input('anb');
            $relationship_code = $request->input('relationship_code');
            $category = $relationship_code;
            $plan_premium_table = 1;
            $plan_rate_basis = 1000;//DbHelper::getColumnValue('planinfo','PlanOldName',$plan_code,'rate_basis');
            $life_premium = 0;
            $dp_premium = 0;
            //$pol_fee  = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'policy_fee');
            
            
            $res = array();
            $plan_id = DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code'); 

            $qry = $this->smartlife_db->table('plan_rider_config as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_id,'p.rider_code' => '03'));
            $results_prem = $qry->first();
            if($results_prem){
                $plan_rider_rate = $results_prem->rate;
                $plan_rider_rate_two = $results_prem->rate2;
            }

            DbHelper::getColumnValue('planinfo', 'PlanOldName',$plan_code,'plan_code');  
            $msg = 'Premium Calculated successfully';
            if ($relationship_code == "B") {
                //spouse
                $dp_premium = ($sum_assured * $plan_rider_rate) / $plan_rate_basis;
            } else if ($relationship_code == "C") {
                //child
                $dp_premium = ($sum_assured * $plan_rider_rate_two) / $plan_rate_basis;
            } else if ($relationship_code == "D") {
                //parent
                $qry = $this->smartlife_db->table('parentspremratesinfo as p')
                ->select('*')
                ->where(array('p.plan_code' => $plan_code,'p.sumAssured' => $sum_assured));
                $results_prem = $qry->first();
                $results_prem;
                if($results_prem){
                    $dp_premium = $results_prem->premiumRate;
                }
            } else {
                $msg = "Not Applicable";
            }
            
            //if success it will return premium per month plus suspense account.
            $res = array(
                'success' => true,
                'dp_premium' => number_format((float)$dp_premium, 2, '.', ''),
                'message' => $msg
            );
        }catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        }catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }
    */
}
