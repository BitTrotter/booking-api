<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Aws\Sns\SnsClient;
use App\Models\MedicalRecord;
use Illuminate\Console\Command;

class NotificationScheduleMenssajeText extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notification-schedule-message-text';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluación de los servicios, para notificar al cliente 1 hora antes de la atención, con un mensaje de texto al celular';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        date_default_timezone_set('America/Lima');
        //
        $today_date = now()->format("Y-m-d");
        $medical_records = MedicalRecord::whereDate("event_date",$today_date)
                                            ->where("cron_state",0)
                                            ->get();
        // dd($medical_records);
        foreach ($medical_records as $key => $medical_record) {
            // ESTADO DE SERVICO - PENDIENTE
            // HORA
            $resource = null;
            if($medical_record->appointment_id){
                $resource = $medical_record->appointment;
            }
            if($medical_record->vaccination_id){
                $resource = $medical_record->vaccination;
            }
            if($medical_record->surgerie_id){
                $resource = $medical_record->surgerie;
            }
            if($resource->state == 1){
                
                // OBTENEMOS EL TIEMPO ACTUAL Y CAPTURAMOS LA HORA
                $time_current = now();// 2024-11-20 13:50:23
                $hour = $time_current->format("h");// 13

                // CALCULAR LA HORA DE INICIO DEL SERVICIO
                $hour_start = "";
                $schedule_hour_start = $resource->schedules->sortBy("veterinarie_schedule_hour_id")->first();
                if($schedule_hour_start){
                    $hour_start = Carbon::parse(date("Y-m-d")." ".$schedule_hour_start->schedule_hour->hour_start)->format("h");//14 - 15
                }
                // dd($hour,$hour_start);
                if($hour == ($hour_start- 1)){
                    // PUEDO ENVIAR EL MENSAJE DE TEXTO O ENVIAR EL CORREO
                    if($medical_record->pet->owner->phone){
                        // dd($medical_record->pet->owner->email);
                        $data = [
                            "full_name" => $medical_record->pet->owner->first_name.' '.$medical_record->pet->owner->last_name,
                            "name_pet" => $medical_record->pet->name,
                            "imagen" => env("APP_URL")."storage/".$medical_record->pet->photo,
                            "event_type" => $medical_record->event_type,
                            "event_date" => Carbon::parse($medical_record->event_date)->format("Y/m/d"),
                            "hour_start" => Carbon::parse(date("Y-m-d")." ".$schedule_hour_start->schedule_hour->hour_start)->format("h:i A")
                        ];


                        $params = array(
                            "credentials" => array(
                                'key' => env("AWS_ACCESS_KEY_ID"),
                                'secret' => env("AWS_SECRET_ACCESS_KEY"),
                            ),
                            'region' => env("AWS_DEFAULT_REGION"),
                            'version' => 'latest'
                        );
                
                        $sns = new SnsClient($params);
                        
                        $type_service = "";
                        switch ($data["event_type"]) {
                            case 1:
                                $type_service = "Citas medicas";
                                break;
                            case 2:
                                $type_service = "Vacunación";
                                break;
                            case 3:
                                $type_service = "Cirujía";
                                break;
                            default:
                                # code...
                                break;
                        }
                        $args = array(
                            'MessageAttributes' => [
                                'AWS.SNS.SMS.SenderID' => [
                                    'DataType' => 'String',
                                    'StringValue' => 'Laravest'
                                ],
                                // 'AWS.SNS.SMS.MaxPrice' => [
                                //     'DataType' => 'Number',
                                //     'StringValue' => '0.50'
                                // ],
                                'AWS.SNS.SMS.SMSType' => [
                                    'DataType' => 'String',
                                    'StringValue' => 'Transactional'
                                ],
                            ],
                            "Message" => "Hola ".$data["full_name"]." recuerda que tienes una ".$type_service." para tu mascotita ".$data["name_pet"]." para el dia ".$data["event_date"]." a la hora de ".$data["hour_start"],
                            "PhoneNumber" => env("AWS_PHONE"),//$medical_record->pet->owner->phone
                        );
                
                        $result = $sns->publish($args);

                        $medical_record->update(["cron_state" => 1]);
                    }
                }
            }
        }
    }
}
